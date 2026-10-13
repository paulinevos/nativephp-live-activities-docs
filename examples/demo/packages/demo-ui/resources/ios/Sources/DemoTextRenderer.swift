import SwiftUI

// The fixture needs only text; containers and press gestures are core primitives.
// Keep this renderer in the example, independent of the distributed activity plugin.
struct DemoTextRenderer: View {
    let node: NativeUINode

    var body: some View {
        Text(node.props.getString("text"))
            .font(.system(size: CGFloat(node.props.getFloat("font_size", default: 17)),
                          weight: node.props.getInt("font_weight", default: 3) >= 6 ? .bold : .regular))
            .foregroundStyle(Color(argb: node.props.getColor("color")))
            .fixedSize(horizontal: false, vertical: true)
    }
}
