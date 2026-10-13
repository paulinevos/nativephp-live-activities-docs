package com.vos.liveactivities

import android.content.Context
import android.graphics.drawable.Icon
import org.json.JSONObject
import kotlin.math.roundToInt

internal object ActivityPresentation {
    fun icon(context: Context, content: JSONObject): Icon =
        Icon.createWithBitmap(ActivityIcons.bitmap(content.optString("icon", "circle-dashed")))

    fun criticalText(content: JSONObject): String =
        "${(content.getDouble("progress") * 100).roundToInt()}%"
}
