package com.vos.demoui

import android.Manifest
import android.app.Activity
import android.app.NotificationManager
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.provider.Settings
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse

object DemoNotifications {
    class Request(private val activity: Activity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            if (parameters.isNotEmpty()) return BridgeResponse.error("invalid_payload", "No parameters expected")
            val manager = activity.getSystemService(NotificationManager::class.java)
            if (manager.areNotificationsEnabled()) return BridgeResponse.success(mapOf("message" to "Notifications are enabled. Start a preview."))
            activity.runOnUiThread {
                if (Build.VERSION.SDK_INT >= 33 && activity.checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
                    activity.requestPermissions(arrayOf(Manifest.permission.POST_NOTIFICATIONS), 7461)
                } else {
                    activity.startActivity(Intent(Settings.ACTION_APP_NOTIFICATION_SETTINGS).putExtra(Settings.EXTRA_APP_PACKAGE, activity.packageName))
                }
            }
            return BridgeResponse.success(mapOf("message" to "Allow notifications, then start a preview. If no prompt appears, use notification settings."))
        }
    }

    class OpenSettings(private val activity: Activity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            if (parameters.isNotEmpty()) return BridgeResponse.error("invalid_payload", "No parameters expected")
            // General notification settings remain useful on older Android, and
            // expose Live Updates on system images that support promotion.
            activity.runOnUiThread {
                activity.startActivity(Intent(Settings.ACTION_APP_NOTIFICATION_SETTINGS).putExtra(Settings.EXTRA_APP_PACKAGE, activity.packageName))
            }
            return BridgeResponse.success(mapOf("message" to "Enable notifications and Live Updates where available, then return to the demo."))
        }
    }
}
