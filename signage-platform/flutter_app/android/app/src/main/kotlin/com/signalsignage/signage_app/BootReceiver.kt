package com.signalsignage.signage_app

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent

/**
 * After a reboot, reopen the app if this device was used as a display
 * ("Show Display" code mode or managed mode), or if it is the dedicated player build.
 * Phones/tablets used for management are left alone.
 */
class BootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        // shared_preferences (Flutter) stores keys with a "flutter." prefix in this file.
        val prefs = context.getSharedPreferences("FlutterSharedPreferences", Context.MODE_PRIVATE)
        val displayMode = prefs.getString("flutter.display_mode", null)
        val isPlayerBuild = context.packageName.endsWith(".player")
        if (displayMode == null && !isPlayerBuild) return
        val launch = Intent(context, MainActivity::class.java).apply {
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP)
        }
        context.startActivity(launch)
    }
}
