import ActivityKit
import SwiftUI
import WidgetKit

@main
struct LiveActivitiesBundle: WidgetBundle {
    var body: some Widget { LiveActivitiesWidget() }
}

struct LiveActivitiesWidget: Widget {
    var body: some WidgetConfiguration {
        ActivityConfiguration(for: LiveActivityAttributes.self) { context in
            HStack(spacing: 14) {
                ActivitySymbol(state: context.state, size: 28)
                VStack(alignment: .leading, spacing: 8) {
                    Text(context.state.title).font(.headline)
                    Text(context.state.subtitle).font(.subheadline)
                    ProgressView(value: context.state.progress).tint(context.state.accent)
                }
            }
            .padding()
            .activityBackgroundTint(Color.black.opacity(0.85))
            .activitySystemActionForegroundColor(.white)
            .foregroundStyle(.white)
        } dynamicIsland: { context in
            DynamicIsland {
                DynamicIslandExpandedRegion(.leading) { ActivitySymbol(state: context.state, size: 22) }
                DynamicIslandExpandedRegion(.trailing) {
                    Text(context.state.progress, format: .percent.precision(.fractionLength(0)))
                        .foregroundStyle(context.state.accent)
                }
                DynamicIslandExpandedRegion(.center) { Text(context.state.title).font(.headline) }
                DynamicIslandExpandedRegion(.bottom) {
                    VStack { Text(context.state.subtitle); ProgressView(value: context.state.progress).tint(context.state.accent) }
                }
            } compactLeading: {
                ActivitySymbol(state: context.state, size: 18)
            } compactTrailing: {
                Text(context.state.progress, format: .percent.precision(.fractionLength(0)))
                    .foregroundStyle(context.state.accent)
            } minimal: {
                ActivitySymbol(state: context.state, size: 18)
            }
        }
    }
}

private struct ActivitySymbol: View {
    let state: LiveActivityAttributes.ContentState
    let size: CGFloat

    var body: some View {
        Image(uiImage: LiveActivityIcons.image(state.icon ?? "circle-dashed"))
            .renderingMode(.template).resizable().scaledToFit()
            .frame(width: size, height: size)
            .foregroundStyle(state.accent)
            .accessibilityHidden(true)
    }
}

private extension LiveActivityAttributes.ContentState {
    var accent: Color {
        guard let color, let rgb = UInt32(color.dropFirst(), radix: 16) else { return .white }
        return Color(red: Double((rgb >> 16) & 255) / 255,
                     green: Double((rgb >> 8) & 255) / 255,
                     blue: Double(rgb & 255) / 255)
    }
}
