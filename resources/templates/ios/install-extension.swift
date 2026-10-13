import Foundation
import CryptoKit

// Uses Apple's plist parser instead of depending on the Ruby xcodeproj gem.
// Stable IDs and replacement by ID make repeated compilation idempotent.
let arguments = CommandLine.arguments
func fail(_ message: String) -> Never {
    FileHandle.standardError.write(Data((message + "\n").utf8))
    exit(1)
}
guard arguments.count == 4 else { fail("Expected build path, plugin path and application ID") }
let build = URL(fileURLWithPath: arguments[1])
let plugin = URL(fileURLWithPath: arguments[2])
let appID = arguments[3]
guard appID.range(of: "^[A-Za-z0-9-]+(\\.[A-Za-z0-9-]+)+$", options: .regularExpression) != nil else { fail("Invalid application bundle ID") }
let projectURL = build.appendingPathComponent("NativePHP.xcodeproj/project.pbxproj")
func readPlist(_ url: URL) throws -> [String: Any] {
    guard let value = try PropertyListSerialization.propertyList(from: Data(contentsOf: url), format: nil) as? [String: Any] else {
        fail("Expected plist dictionary: \(url.path)")
    }
    return value
}
func stableID(_ label: String) -> String {
    SHA256.hash(data: Data(("paulinevos.live-activities." + label).utf8)).prefix(12).map { String(format: "%02X", $0) }.joined()
}
func quoted(_ value: String) -> String {
    if !value.isEmpty && value.range(of: "^[A-Za-z0-9_./]+$", options: .regularExpression) != nil { return value }
    return "\"" + value.replacingOccurrences(of: "\\", with: "\\\\").replacingOccurrences(of: "\"", with: "\\\"").replacingOccurrences(of: "\n", with: "\\n").replacingOccurrences(of: "\r", with: "\\r").replacingOccurrences(of: "\t", with: "\\t") + "\""
}
var referenceLabels: [String: String] = [:]
func reference(_ value: String) -> String {
    guard let label = referenceLabels[value] else { return quoted(value) }
    return quoted(value) + " /* " + label + " */"
}
func serialize(_ value: Any, depth: Int = 0) -> String {
    let indent = String(repeating: "\t", count: depth)
    if let object = value as? [String: Any] {
        return "{\n" + object.keys.sorted().map { indent + "\t" + reference($0) + " = " + ($0 == "objects" ? serializeObjects(object[$0] as! [String: [String: Any]]) : serialize(object[$0]!, depth: depth + 1)) + ";\n" }.joined() + indent + "}"
    }
    if let array = value as? [Any] {
        return "(\n" + array.map { indent + "\t" + serialize($0, depth: depth + 1) + ",\n" }.joined() + indent + ")"
    }
    return reference(String(describing: value))
}
func serializeObjects(_ objects: [String: [String: Any]]) -> String {
    let groups = Dictionary(grouping: objects.keys, by: { objects[$0]?["isa"] as? String ?? "Unknown" })
    return "{\n" + groups.keys.sorted().map { kind in
        "\n/* Begin " + kind + " section */\n" + groups[kind]!.sorted().map { id in
            "\t\t" + reference(id) + " = " + serialize(objects[id]!, depth: 2) + ";\n"
        }.joined() + "/* End " + kind + " section */\n"
    }.joined() + "\t}"
}
func appended(_ object: [String: Any], _ key: String, _ id: String) -> [String: Any] {
    var result = object
    var values = result[key] as? [String] ?? []
    if !values.contains(id) { values.append(id) }
    result[key] = values
    return result
}

do {
    let original = try String(contentsOf: projectURL, encoding: .utf8)
    let comments = try NSRegularExpression(pattern: "([A-F0-9]{24}) /\\* ([^*]+) \\*/")
    for match in comments.matches(in: original, range: NSRange(original.startIndex..., in: original)) {
        let identifier = String(original[Range(match.range(at: 1), in: original)!])
        referenceLabels[identifier] = String(original[Range(match.range(at: 2), in: original)!])
    }
    var project = try readPlist(projectURL)
    guard var objects = project["objects"] as? [String: [String: Any]],
          let rootID = project["rootObject"] as? String,
          var root = objects[rootID], let mainGroupID = root["mainGroup"] as? String,
          var mainGroup = objects[mainGroupID] else { fail("Unsupported Xcode project structure") }
    let hosts = objects.filter { $0.value["productType"] as? String == "com.apple.product-type.application" }.sorted { $0.key < $1.key }
    guard !hosts.isEmpty else { fail("No application targets found") }
    let folder = build.appendingPathComponent("LiveActivitiesExtension")
    try FileManager.default.createDirectory(at: folder, withIntermediateDirectories: true)
    for (source, destination) in [("resources/ios/Sources/LiveActivityAttributes.swift", "LiveActivityAttributes.swift"), ("resources/ios/Sources/LiveActivityIcons.swift", "LiveActivityIcons.swift"), ("resources/templates/ios/LiveActivitiesWidget.swift", "LiveActivitiesWidget.swift")] {
        try Data(contentsOf: plugin.appendingPathComponent(source)).write(to: folder.appendingPathComponent(destination), options: .atomic)
    }
    let plist: [String: Any] = ["CFBundleDisplayName": "Live Activities", "NSExtension": ["NSExtensionPointIdentifier": "com.apple.widgetkit-extension"]]
    try PropertyListSerialization.data(fromPropertyList: plist, format: .xml, options: 0).write(to: folder.appendingPathComponent("Info.plist"), options: .atomic)
    let groupID = stableID("group")
    let sourceNames = ["LiveActivityAttributes.swift", "LiveActivityIcons.swift", "LiveActivitiesWidget.swift"]
    for name in sourceNames {
        objects[stableID(name)] = ["isa": "PBXFileReference", "lastKnownFileType": "sourcecode.swift", "path": name, "sourceTree": "<group>"]
    }
    objects[groupID] = ["isa": "PBXGroup", "children": sourceNames.map(stableID), "path": "LiveActivitiesExtension", "sourceTree": "<group>"]
    mainGroup = appended(mainGroup, "children", groupID)
    objects[mainGroupID] = mainGroup
    for (hostID, originalHost) in hosts {
        var host = originalHost
        guard let hostName = host["name"] as? String,
              let configListID = host["buildConfigurationList"] as? String,
              let configurations = objects[configListID]?["buildConfigurations"] as? [String] else { fail("Application configuration missing") }
        // Separate extension targets avoid incompatible device/simulator build settings.
        let name = hostName + "LiveActivities"
        let targetID = stableID(name)
        let productID = stableID(name + ".appex")
        let sourcesID = stableID(name + ".sources")
        let extensionConfigListID = stableID(name + ".configs")
        var extensionConfigs: [String] = []
        for configID in configurations {
            guard var config = objects[configID], var settings = config["buildSettings"] as? [String: Any], let configName = config["name"] as? String else { fail("Application build settings missing") }
            settings["INFOPLIST_KEY_NSSupportsLiveActivities"] = "YES"
            config["buildSettings"] = settings
            objects[configID] = config
            if let path = settings["INFOPLIST_FILE"] as? String, !path.contains("$(") {
                let hostPlist = build.appendingPathComponent(path)
                if FileManager.default.fileExists(atPath: hostPlist.path) {
                    var info = try readPlist(hostPlist)
                    info["NSSupportsLiveActivities"] = true
                    try PropertyListSerialization.data(fromPropertyList: info, format: .xml, options: 0).write(to: hostPlist, options: .atomic)
                }
            }
            var extensionSettings: [String: Any] = ["APPLICATION_EXTENSION_API_ONLY": "YES", "CLANG_ENABLE_MODULES": "YES", "CODE_SIGN_STYLE": "Automatic", "CURRENT_PROJECT_VERSION": settings["CURRENT_PROJECT_VERSION"] ?? "1", "MARKETING_VERSION": settings["MARKETING_VERSION"] ?? "1.0", "GENERATE_INFOPLIST_FILE": "YES", "INFOPLIST_FILE": "LiveActivitiesExtension/Info.plist", "IPHONEOS_DEPLOYMENT_TARGET": "16.2", "PRODUCT_BUNDLE_IDENTIFIER": appID + ".liveactivities", "PRODUCT_NAME": "$(TARGET_NAME)", "SDKROOT": "iphoneos", "SKIP_INSTALL": "YES", "SWIFT_VERSION": "5.0", "TARGETED_DEVICE_FAMILY": "1,2", "LD_RUNPATH_SEARCH_PATHS": ["$(inherited)", "@executable_path/Frameworks", "@executable_path/../../Frameworks"]]
            for setting in ["DEVELOPMENT_TEAM", "CODE_SIGN_IDENTITY", "SUPPORTED_PLATFORMS", "SUPPORTS_MACCATALYST"] {
                extensionSettings[setting] = settings[setting]
            }
            let extensionConfigID = stableID(name + "." + configName)
            objects[extensionConfigID] = ["isa": "XCBuildConfiguration", "name": configName, "buildSettings": extensionSettings]
            extensionConfigs.append(extensionConfigID)
        }
        objects[extensionConfigListID] = ["isa": "XCConfigurationList", "buildConfigurations": extensionConfigs, "defaultConfigurationIsVisible": "0", "defaultConfigurationName": "Release"]
        let buildFiles = sourceNames.map { source -> String in
            let id = stableID(name + "." + source)
            objects[id] = ["isa": "PBXBuildFile", "fileRef": stableID(source)]
            return id
        }
        objects[sourcesID] = ["isa": "PBXSourcesBuildPhase", "buildActionMask": "2147483647", "files": buildFiles, "runOnlyForDeploymentPostprocessing": "0"]
        objects[productID] = ["isa": "PBXFileReference", "explicitFileType": "wrapper.app-extension", "includeInIndex": "0", "path": name + ".appex", "sourceTree": "BUILT_PRODUCTS_DIR"]
        objects[targetID] = ["isa": "PBXNativeTarget", "buildConfigurationList": extensionConfigListID, "buildPhases": [sourcesID], "buildRules": [], "dependencies": [], "name": name, "productName": name, "productReference": productID, "productType": "com.apple.product-type.app-extension"]
        let dependencyID = stableID(name + ".dependency")
        let proxyID = stableID(name + ".proxy")
        objects[proxyID] = ["isa": "PBXContainerItemProxy", "containerPortal": rootID, "proxyType": "1", "remoteGlobalIDString": targetID, "remoteInfo": name]
        objects[dependencyID] = ["isa": "PBXTargetDependency", "target": targetID, "targetProxy": proxyID]
        host = appended(host, "dependencies", dependencyID)
        let embedID = stableID(name + ".embed")
        let embedFileID = stableID(name + ".embedfile")
        objects[embedFileID] = ["isa": "PBXBuildFile", "fileRef": productID, "settings": ["ATTRIBUTES": ["RemoveHeadersOnCopy"]]]
        objects[embedID] = ["isa": "PBXCopyFilesBuildPhase", "buildActionMask": "2147483647", "dstPath": "", "dstSubfolderSpec": "13", "files": [embedFileID], "name": "Embed Live Activities", "runOnlyForDeploymentPostprocessing": "0"]
        host = appended(host, "buildPhases", embedID)
        objects[hostID] = host
        root = appended(root, "targets", targetID)
        if let productsID = root["productRefGroup"] as? String, let products = objects[productsID] { objects[productsID] = appended(products, "children", productID) }
    }
    objects[rootID] = root
    for (id, object) in objects where referenceLabels[id] == nil {
        referenceLabels[id] = (object["name"] ?? object["path"] ?? object["isa"]) as? String
    }
    project["objects"] = objects
    let serialized = "// !$*UTF8*$!\n" + serialize(project) + "\n"
    // Validate before replacing the project, leaving the original intact on parse failure.
    _ = try PropertyListSerialization.propertyList(from: Data(serialized.utf8), format: nil)
    try Data(serialized.utf8).write(to: projectURL, options: .atomic)
    print("Installed Live Activities extensions for \(hosts.count) application targets")
} catch { fail("Live Activities installation failed: \(error.localizedDescription)") }
