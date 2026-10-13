import ActivityKit
import Foundation

@available(iOS 16.2, *)
struct LiveActivityAttributes: ActivityAttributes {
    struct ContentState: Codable, Hashable {
        var title: String
        var subtitle: String
        var progress: Double
        var icon: String? = nil
        var color: String? = nil

        var dictionary: [String: Any] {
            var content: [String: Any] = ["title": title, "subtitle": subtitle, "progress": progress]
            if let icon { content["icon"] = icon }
            if let color { content["color"] = color }
            return content
        }
    }
    var key: String
}
