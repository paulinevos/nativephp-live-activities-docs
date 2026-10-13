package com.vos.demoui

import androidx.compose.foundation.text.BasicText
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp
import com.nativephp.mobile.ui.nativerender.NativeUINode

object DemoTextRenderer {
    @Composable
    fun Render(node: NativeUINode, modifier: Modifier) {
        BasicText(
            text = node.props.getString("text"),
            modifier = modifier,
            style = TextStyle(
                color = Color(node.props.getColor("color")),
                fontSize = node.props.getFloat("font_size", 17f).sp,
                fontWeight = if (node.props.getInt("font_weight", 3) >= 6) FontWeight.Bold else FontWeight.Normal
            )
        )
    }
}
