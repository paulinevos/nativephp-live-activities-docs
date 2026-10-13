package com.vos.liveactivities

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.os.Build
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse
import org.json.JSONObject
import java.util.UUID

/** Context constructors also work in NativePHP's cold-boot bridge registry. */
object LiveActivitiesFunctions {
    class Start(context: Context) : Operation(context, "start")
    class Update(context: Context) : Operation(context, "update")
    class End(context: Context) : Operation(context, "end")
    class List(context: Context) : Operation(context, "list")
    class Capabilities(context: Context) : Operation(context, "capabilities")

    open class Operation(context: Context, private val operation: String) : BridgeFunction {
        private val store = ActivityStore(context.applicationContext)

        override fun execute(parameters: Map<String, Any>): Map<String, Any> = synchronized(lock) {
            try {
                BridgeResponse.success(store.execute(operation, parameters))
            } catch (error: ActivityFailure) {
                BridgeResponse.error(error.code, error.message ?: "Activity operation failed")
            } catch (_: SecurityException) {
                BridgeResponse.error("permission_denied", "Allow notifications in the app's system settings")
            } catch (_: Exception) {
                BridgeResponse.error("native_error", "Android could not complete the activity operation")
            }
        }
    }

    private val lock = Any()
}

internal class ActivityFailure(val code: String, message: String) : Exception(message)

internal object ActivityPayload {
    fun fields(values: Map<String, *>, expected: Set<String>) {
        if (values.keys != expected) fail("Expected fields: ${expected.sorted().joinToString()}")
    }

    fun string(value: Any?, name: String, maximum: Int, empty: Boolean = false): String {
        val text = value as? String ?: fail("$name must be a string")
        if ((!empty && text.isBlank()) || text.codePointCount(0, text.length) > maximum) {
            fail("$name must ${if (empty) "" else "not be blank and "}contain at most $maximum characters")
        }
        return text
    }

    fun content(value: Any?): JSONObject {
        val json = value as? JSONObject ?: fail("content must be an object")
        val keys = json.keys().asSequence().toSet()
        val required = setOf("title", "subtitle", "progress")
        if (!keys.containsAll(required) || !(required + setOf("icon", "color")).containsAll(keys)) {
            fail("Unexpected or missing content fields")
        }
        val title = string(json.opt("title"), "title", 120)
        val subtitle = string(json.opt("subtitle"), "subtitle", 240, true)
        val progress = (json.opt("progress") as? Number)?.toDouble() ?: fail("progress must be a number")
        if (!progress.isFinite() || progress !in 0.0..1.0) fail("progress must be between 0 and 1")
        val content = JSONObject().put("title", title).put("subtitle", subtitle).put("progress", progress)
        if (json.has("icon")) {
            val icon = json.opt("icon") as? String ?: fail("icon must be a string")
            if (!ActivityIcons.contains(icon)) fail("Unknown activity icon")
            content.put("icon", icon)
        }
        if (json.has("color")) {
            val color = json.opt("color") as? String ?: fail("color must be a string")
            if (!Regex("#[0-9a-fA-F]{6}").matches(color)) fail("Color must be a six-digit hex color")
            content.put("color", color.uppercase(java.util.Locale.ROOT))
        }
        return content
    }

    private fun fail(message: String): Nothing = throw ActivityFailure("invalid_payload", message)
}

private class ActivityStore(private val context: Context) {
    private val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
    private val preferences = context.getSharedPreferences("paulinevos_live_activities_v1", Context.MODE_PRIVATE)
    private val channel = "paulinevos_live_activities"
    private val tagPrefix = "paulinevos.liveactivities."

    fun execute(operation: String, parameters: Map<String, Any>): Map<String, Any> {
        val expected = when (operation) {
            "start" -> setOf("key", "content")
            "update" -> setOf("id", "content")
            "end" -> setOf("id")
            else -> emptySet()
        }
        ActivityPayload.fields(parameters, expected)
        if (operation == "capabilities") return mapOf(
            "platform" to "android", "supported" to (Build.VERSION.SDK_INT >= 24),
            "enabled" to enabled(), "push" to false, "promoted" to (enabled() && promotionAllowed())
        )
        if (Build.VERSION.SDK_INT < 24) throw ActivityFailure("unavailable", "Android 7 or newer is required")
        val activities = active()
        return when (operation) {
            "start" -> start(parameters, activities)
            "update" -> update(parameters, activities)
            "end" -> end(parameters, activities)
            else -> mapOf("activities" to activities.map { wire(it.value) })
        }
    }

    private fun start(parameters: Map<String, Any>, activities: MutableMap<String, JSONObject>): Map<String, Any> {
        val key = ActivityPayload.string(parameters["key"], "key", 80)
        val content = ActivityPayload.content(parameters["content"])
        if (activities.values.any { it.getString("key") == key }) {
            throw ActivityFailure("duplicate_activity", "An active activity already uses this key")
        }
        requireEnabled()
        val id = UUID.randomUUID().toString()
        val activity = JSONObject().put("id", id).put("key", key).put("content", content).put("state", "active")
        // Save before posting: a failed commit must never leave an untracked notification.
        save(id, activity)
        try {
            post(activity)
        } catch (error: Exception) {
            remove(id)
            throw error
        }
        return mapOf("activity" to wire(activity))
    }

    private fun update(parameters: Map<String, Any>, activities: MutableMap<String, JSONObject>): Map<String, Any> {
        val id = ActivityPayload.string(parameters["id"], "id", 128)
        val content = ActivityPayload.content(parameters["content"])
        val previous = activities[id] ?: missing()
        requireEnabled()
        val next = JSONObject(previous.toString()).put("content", content)
        save(id, next)
        try {
            post(next)
        } catch (error: Exception) {
            save(id, previous)
            throw error
        }
        return mapOf("activity" to wire(next))
    }

    private fun end(parameters: Map<String, Any>, activities: MutableMap<String, JSONObject>): Map<String, Any> {
        val id = ActivityPayload.string(parameters["id"], "id", 128)
        if (!activities.containsKey(id)) missing()
        manager.cancel(tagPrefix + id, 1)
        remove(id)
        return mapOf("id" to id, "state" to "ended")
    }

    /** System notifications are authoritative, including user dismissal and reboot. */
    private fun active(): MutableMap<String, JSONObject> {
        val visible = manager.activeNotifications.mapNotNull { item ->
            item.tag?.takeIf { it.startsWith(tagPrefix) && item.id == 1 }?.removePrefix(tagPrefix)
        }.toSet()
        val result = mutableMapOf<String, JSONObject>()
        preferences.all.forEach { (id, value) ->
            if (id !in visible) {
                remove(id)
            } else {
                result[id] = JSONObject(value as String)
            }
        }
        return result
    }

    private fun enabled(): Boolean {
        if (Build.VERSION.SDK_INT < 24 || !manager.areNotificationsEnabled()) return false
        if (Build.VERSION.SDK_INT >= 26 && manager.getNotificationChannel(channel)?.importance == NotificationManager.IMPORTANCE_NONE) return false
        return true
    }

    private fun requireEnabled() {
        if (!enabled()) throw ActivityFailure("permission_denied", "Allow notifications and the Live activities channel in system settings")
    }

    // Capability detection handles both API 36 and 36.1 without raising the host compile SDK.
    private fun promotionAllowed(): Boolean {
        if (Build.VERSION.SDK_INT < 36) return false
        return try {
            Notification.Builder::class.java.getMethod("setRequestPromotedOngoing", Boolean::class.javaPrimitiveType)
            NotificationManager::class.java.getMethod("canPostPromotedNotifications").invoke(manager) == true
        } catch (_: ReflectiveOperationException) {
            false
        }
    }

    @Suppress("DEPRECATION")
    private fun post(activity: JSONObject) {
        val content = activity.getJSONObject("content")
        val builder = if (Build.VERSION.SDK_INT >= 26) {
            manager.createNotificationChannel(NotificationChannel(channel, "Live activities", NotificationManager.IMPORTANCE_LOW))
            Notification.Builder(context, channel)
        } else Notification.Builder(context).setPriority(Notification.PRIORITY_LOW)
        builder.setSmallIcon(ActivityPresentation.icon(context, content))
            .setContentTitle(content.getString("title"))
            .setContentText(content.getString("subtitle"))
            .setProgress(1000, (content.getDouble("progress") * 1000).toInt(), false)
            .setOngoing(true).setOnlyAlertOnce(true).setShowWhen(false)
            .setCategory(Notification.CATEGORY_PROGRESS)
        if (content.has("color")) builder.setColor(android.graphics.Color.parseColor(content.getString("color")))
        context.packageManager.getLaunchIntentForPackage(context.packageName)?.let { intent ->
            builder.setContentIntent(PendingIntent.getActivity(context, 0, intent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE))
        }
        if (Build.VERSION.SDK_INT >= 36) {
            try {
                Notification.Builder::class.java.getMethod("setShortCriticalText", String::class.java)
                    .invoke(builder, ActivityPresentation.criticalText(content))
            } catch (_: ReflectiveOperationException) {
                // Older platform builds keep the normal progress notification.
            }
        }
        if (promotionAllowed()) {
            try {
                Notification.Builder::class.java.getMethod("setRequestPromotedOngoing", Boolean::class.javaPrimitiveType).invoke(builder, true)
            } catch (_: ReflectiveOperationException) {
                // Promotion is optional; the standard progress notification remains useful.
            }
        }
        manager.notify(tagPrefix + activity.getString("id"), 1, builder.build())
    }

    private fun save(id: String, activity: JSONObject) {
        if (!preferences.edit().putString(id, activity.toString()).commit()) throw ActivityFailure("native_error", "Could not save activity state")
    }

    private fun remove(id: String) {
        if (!preferences.edit().remove(id).commit()) throw ActivityFailure("native_error", "Could not remove activity state")
    }

    private fun wire(activity: JSONObject): Map<String, Any> {
        val content = activity.getJSONObject("content")
        return mapOf("id" to activity.getString("id"), "key" to activity.getString("key"), "state" to "active",
            "content" to content.keys().asSequence().associateWith { content.get(it) })
    }

    private fun missing(): Nothing = throw ActivityFailure("activity_not_found", "Activity is no longer active")
}
