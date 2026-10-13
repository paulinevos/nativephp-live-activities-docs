package com.vos.liveactivities

import android.app.NotificationManager
import android.content.Context
import androidx.test.platform.app.InstrumentationRegistry
import androidx.test.ext.junit.runners.AndroidJUnit4
import org.json.JSONObject
import org.junit.Assert.*
import org.junit.Assume.assumeTrue
import org.junit.Test
import org.junit.runner.RunWith

/** Copy to the example app's androidTest source set; run on API 24+. */
@RunWith(AndroidJUnit4::class)
class LiveActivitiesInstrumentedTest {
    private val context = InstrumentationRegistry.getInstrumentation().targetContext
    private fun content(title: String = "Delivery") = JSONObject()
        .put("title", title).put("subtitle", "On its way").put("progress", 0.5)

    @Test fun rejectsUnknownFieldsAndMalformedContent() {
        val start = LiveActivitiesFunctions.Start(context)
        val invalid = listOf(
            mapOf("key" to "test", "content" to content(), "unknown" to true),
            mapOf("key" to "test", "content" to "not an object"),
            mapOf("key" to "test", "content" to content().put("progress", "0.5")),
            mapOf("key" to "test", "content" to content().put("progress", 2)),
            mapOf("key" to "test", "content" to content().put("extra", true)),
            mapOf("key" to "test", "content" to content().put("icon", "not-an-icon")),
            mapOf("key" to "test", "content" to content().put("icon", JSONObject.NULL)),
            mapOf("key" to "test", "content" to content().put("color", "#fff")),
            mapOf("key" to "test", "content" to content().put("color", "#123456\n")),
            mapOf("key" to "test", "content" to content().put("color", JSONObject.NULL)),
            mapOf("key" to "test", "content" to content(" ")),
            mapOf("key" to "test", "content" to content("😀".repeat(121)))
        )
        invalid.forEach { payload -> assertEquals("invalid_payload", start.execute(payload)["code"]) }
        assertEquals("invalid_payload", LiveActivitiesFunctions.List(context).execute(mapOf("unexpected" to true))["code"])
    }

    @Test fun deniedPermissionIsAnExplicitFailure() {
        assumeTrue("Run once with notifications disabled to exercise this case",
            LiveActivitiesFunctions.Capabilities(context).execute(emptyMap())["enabled"] == false)
        assertEquals("permission_denied", LiveActivitiesFunctions.Start(context)
            .execute(mapOf("key" to "permission-${System.nanoTime()}", "content" to content()))["code"])
    }

    @Test fun unknownIdsAreNotSuccessful() {
        assertEquals("activity_not_found", LiveActivitiesFunctions.End(context).execute(mapOf("id" to "unknown"))["code"])
        assertEquals("activity_not_found", LiveActivitiesFunctions.Update(context).execute(mapOf("id" to "unknown", "content" to content()))["code"])
    }

    @Test fun preservesLifecycleAcrossBridgeInstancesAndObservesDismissal() {
        assumeTrue("Grant notification permission and enable the channel first",
            LiveActivitiesFunctions.Capabilities(context).execute(emptyMap())["enabled"] == true)
        val key = "instrumentation-${System.nanoTime()}"
        val started = LiveActivitiesFunctions.Start(context).execute(mapOf("key" to key, "content" to content("😀".repeat(120))))
        assertFalse(started.toString(), started.containsKey("code"))
        val activity = started["activity"] as Map<*, *>
        val id = activity["id"] as String
        try {
            assertEquals("duplicate_activity", LiveActivitiesFunctions.Start(context).execute(mapOf("key" to key, "content" to content()))["code"])
            val updated = LiveActivitiesFunctions.Update(context).execute(mapOf("id" to id, "content" to content("Arriving").put("icon", "truck").put("color", "#06b6d4")))
            assertEquals("Arriving", ((updated["activity"] as Map<*, *>)["content"] as Map<*, *>)["title"])
            assertEquals("truck", ((updated["activity"] as Map<*, *>)["content"] as Map<*, *>)["icon"])
            assertEquals("#06B6D4", ((updated["activity"] as Map<*, *>)["content"] as Map<*, *>)["color"])
            assertTrue(ids().contains(id))
            // Cancellation exercises reconciliation when no delete broadcast is received.
            val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            val notification = manager.activeNotifications.first { it.tag == "paulinevos.liveactivities.$id" }.notification
            assertEquals(android.graphics.Color.parseColor("#06B6D4"), notification.color)
            assertEquals(android.graphics.drawable.Icon.TYPE_BITMAP, notification.smallIcon.type)
            manager.cancel("paulinevos.liveactivities.$id", 1)
            assertFalse(ids().contains(id))
            assertEquals("activity_not_found", LiveActivitiesFunctions.Update(context).execute(mapOf("id" to id, "content" to content()))["code"])
        } finally {
            LiveActivitiesFunctions.End(context).execute(mapOf("id" to id))
        }
    }

    @Test fun endRemovesPresentation() {
        assumeTrue(LiveActivitiesFunctions.Capabilities(context).execute(emptyMap())["enabled"] == true)
        val result = LiveActivitiesFunctions.Start(context).execute(mapOf("key" to "end-${System.nanoTime()}", "content" to content()))
        val id = (result["activity"] as Map<*, *>)["id"] as String
        assertEquals("ended", LiveActivitiesFunctions.End(context).execute(mapOf("id" to id))["state"])
        assertFalse(ids().contains(id))
    }

    private fun ids() = (LiveActivitiesFunctions.List(context).execute(emptyMap())["activities"] as List<*>).map { (it as Map<*, *>)["id"] }
}
