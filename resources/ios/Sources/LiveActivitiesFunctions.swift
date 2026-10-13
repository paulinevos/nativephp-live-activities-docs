import ActivityKit
import CoreFoundation
import Foundation

private struct ActivityFailure: Error {
    let code: String
    let message: String
}

// The synchronous NativePHP bridge runs on its PHP worker thread. Detached tasks
// finish before responding; rejecting main-thread calls avoids semaphore deadlock.
@available(iOS 16.2, *)
private enum ActivityOperations {
    static let lock = NSLock()

    static func fields(_ parameters: [String: Any], _ expected: Set<String>) throws {
        guard Set(parameters.keys) == expected else {
            throw ActivityFailure(code: "invalid_payload", message: "Unexpected or missing activity fields")
        }
    }

    static func string(_ value: Any?, limit: Int, empty: Bool = false) throws -> String {
        guard let value = value as? String, value.unicodeScalars.count <= limit,
              empty || !value.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty else {
            throw ActivityFailure(code: "invalid_payload", message: "Invalid activity text")
        }
        return value
    }

    static func content(_ value: Any?) throws -> LiveActivityAttributes.ContentState {
        guard let value = value as? [String: Any] else {
            throw ActivityFailure(code: "invalid_payload", message: "Content must be an object")
        }
        let required: Set<String> = ["title", "subtitle", "progress"]
        guard required.isSubset(of: Set(value.keys)), Set(value.keys).isSubset(of: required.union(["icon", "color"])) else {
            throw ActivityFailure(code: "invalid_payload", message: "Unexpected or missing content fields")
        }
        var icon: String?
        if let raw = value["icon"] {
            guard let name = raw as? String, LiveActivityIcons.contains(name) else {
                throw ActivityFailure(code: "invalid_payload", message: "Unknown activity icon")
            }
            icon = name
        }
        var color: String?
        if let raw = value["color"] {
            guard let hex = raw as? String, hex.range(of: "^#[0-9a-fA-F]{6}$", options: .regularExpression) != nil, hex.utf8.count == 7 else {
                throw ActivityFailure(code: "invalid_payload", message: "Color must be a six-digit hex color")
            }
            color = hex.uppercased()
        }
        guard let progress = value["progress"] as? NSNumber,
              CFGetTypeID(progress) != CFBooleanGetTypeID(), progress.doubleValue.isFinite,
              (0...1).contains(progress.doubleValue) else {
            throw ActivityFailure(code: "invalid_payload", message: "Progress must be a number between zero and one")
        }
        return try .init(title: string(value["title"], limit: 120),
                         subtitle: string(value["subtitle"], limit: 240, empty: true),
                         progress: progress.doubleValue, icon: icon, color: color)
    }

    static func checkSize(key: String, content: LiveActivityAttributes.ContentState) throws {
        let data = try JSONSerialization.data(withJSONObject: ["key": key, "content": content.dictionary])
        guard data.count <= 3800 else {
            throw ActivityFailure(code: "payload_too_large", message: "Activity exceeds the 3800-byte payload limit")
        }
    }

    static func active() -> [Activity<LiveActivityAttributes>] {
        Activity<LiveActivityAttributes>.activities.filter { $0.activityState == .active || $0.activityState == .stale }
    }

    static func find(_ parameters: [String: Any]) throws -> Activity<LiveActivityAttributes> {
        let id = try string(parameters["id"], limit: 256)
        guard let activity = active().first(where: { $0.id == id }) else {
            throw ActivityFailure(code: "activity_not_found", message: "Activity is no longer active")
        }
        return activity
    }

    static func dictionary(_ activity: Activity<LiveActivityAttributes>) -> [String: Any] {
        ["id": activity.id, "key": activity.attributes.key, "content": activity.content.state.dictionary, "state": "active"]
    }

    static func complete(_ operation: @escaping @Sendable () async -> Void) throws {
        let semaphore = DispatchSemaphore(value: 0)
        let task = Task.detached {
            await operation()
            semaphore.signal()
        }
        guard semaphore.wait(timeout: .now() + 10) == .success else {
            task.cancel()
            throw ActivityFailure(code: "native_error", message: "ActivityKit timed out; outcome is uncertain. List activities before retrying.")
        }
    }

    static func perform(_ operation: String, parameters: [String: Any]) throws -> [String: Any] {
        guard !Thread.isMainThread else {
            throw ActivityFailure(code: "unavailable", message: "Call Live Activities from the NativePHP worker thread")
        }
        lock.lock()
        defer { lock.unlock() }
        switch operation {
        case "Capabilities":
            try fields(parameters, [])
            return ["platform": "ios", "supported": true, "enabled": ActivityAuthorizationInfo().areActivitiesEnabled, "push": false, "promoted": false]
        case "List":
            try fields(parameters, [])
            return ["activities": active().map(dictionary)]
        case "Start":
            try fields(parameters, ["key", "content"])
            let key = try string(parameters["key"], limit: 80)
            let state = try content(parameters["content"])
            try checkSize(key: key, content: state)
            guard ActivityAuthorizationInfo().areActivitiesEnabled else {
                throw ActivityFailure(code: "permission_denied", message: "Live Activities are disabled")
            }
            guard !active().contains(where: { $0.attributes.key == key }) else {
                throw ActivityFailure(code: "duplicate_activity", message: "An activity with this key is already active")
            }
            let activity = try Activity.request(attributes: LiveActivityAttributes(key: key), content: ActivityContent(state: state, staleDate: nil), pushType: nil)
            return ["activity": dictionary(activity)]
        case "Update":
            try fields(parameters, ["id", "content"])
            let activity = try find(parameters)
            let state = try content(parameters["content"])
            try checkSize(key: activity.attributes.key, content: state)
            try complete {
                await activity.update(ActivityContent(state: state, staleDate: nil))
                // ActivityKit's async call may return before the local content
                // observer advances. Preserve read-after-write bridge behavior.
                while activity.content.state != state {
                    if Task.isCancelled { return }
                    try? await Task.sleep(nanoseconds: 20_000_000)
                }
            }
            return ["activity": dictionary(activity)]
        case "End":
            try fields(parameters, ["id"])
            let activity = try find(parameters)
            try complete { await activity.end(nil, dismissalPolicy: .immediate) }
            return ["id": activity.id, "state": "ended"]
        default:
            throw ActivityFailure(code: "invalid_payload", message: "Unknown activity operation")
        }
    }
}

enum LiveActivitiesFunctions {
    private static func execute(_ operation: String, _ parameters: [String: Any]) -> [String: Any] {
        guard #available(iOS 16.2, *) else {
            if operation == "Capabilities", parameters.isEmpty {
                return ["platform": "ios", "supported": false, "enabled": false, "push": false, "promoted": false]
            }
            return BridgeResponse.error(code: "unavailable", message: "Live Activities require iOS 16.2")
        }
        do { return try ActivityOperations.perform(operation, parameters: parameters) }
        catch let error as ActivityFailure { return BridgeResponse.error(code: error.code, message: error.message) }
        catch { return BridgeResponse.error(code: "native_error", message: "ActivityKit rejected the operation: \(error.localizedDescription)") }
    }

    class Start: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] { LiveActivitiesFunctions.execute("Start", parameters) }
    }
    class Update: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] { LiveActivitiesFunctions.execute("Update", parameters) }
    }
    class End: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] { LiveActivitiesFunctions.execute("End", parameters) }
    }
    class List: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] { LiveActivitiesFunctions.execute("List", parameters) }
    }
    class Capabilities: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] { LiveActivitiesFunctions.execute("Capabilities", parameters) }
    }
}
