// Compile-only doubles matching NativePHP 4.5.2's bridge declarations.
protocol BridgeFunction {
    func execute(parameters: [String: Any]) throws -> [String: Any]
}
struct BridgeResponse {
    static func error(code: String, message: String) -> [String: Any] {
        ["status": "error", "code": code, "message": message]
    }
}
