package ru.avtoaliyans.crm.messenger;

import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Intent;
import android.os.Build;
import androidx.annotation.NonNull;
import androidx.core.app.NotificationCompat;
import com.capacitorjs.plugins.pushnotifications.PushNotificationsPlugin;
import com.google.firebase.messaging.FirebaseMessagingService;
import com.google.firebase.messaging.RemoteMessage;
import java.util.Map;
import org.json.JSONObject;

public class TrakloFirebaseMessagingService extends FirebaseMessagingService {
    private static final String ACTION_TRAKLO_PUSH = "TRAKLO_PUSH";

    @Override
    public void onNewToken(@NonNull String token) {
        super.onNewToken(token);
        PushNotificationsPlugin.onNewToken(token);
    }

    @Override
    public void onMessageReceived(@NonNull RemoteMessage remoteMessage) {
        super.onMessageReceived(remoteMessage);
        PushNotificationsPlugin.sendRemoteMessage(remoteMessage);
        showNotificationWithActions(remoteMessage);
    }

    private void showNotificationWithActions(RemoteMessage remoteMessage) {
        Map<String, String> data = remoteMessage.getData();
        if (data.isEmpty()) {
            return;
        }

        String title = firstNonEmpty(
            data.get("title"),
            remoteMessage.getNotification() != null ? remoteMessage.getNotification().getTitle() : null,
            "Traklo"
        );
        String body = firstNonEmpty(
            data.get("body"),
            remoteMessage.getNotification() != null ? remoteMessage.getNotification().getBody() : null,
            ""
        );
        String channelId = firstNonEmpty(data.get("channel_id"), "crm_chat_messages");
        String actionLabel = firstNonEmpty(data.get("push_action_label"), defaultActionLabel(data.get("kind")));

        int notificationId = resolveNotificationId(data, remoteMessage.getMessageId());

        PendingIntent contentIntent = buildPendingIntent(data, "tap", notificationId);
        PendingIntent readIntent = buildPendingIntent(data, "read", notificationId);

        NotificationCompat.Builder builder = new NotificationCompat.Builder(this, channelId)
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(new NotificationCompat.BigTextStyle().bigText(body))
            .setAutoCancel(true)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setContentIntent(contentIntent)
            .addAction(new NotificationCompat.Action.Builder(0, actionLabel, readIntent).build());

        NotificationManager manager = getSystemService(NotificationManager.class);
        if (manager != null) {
            manager.notify(notificationId, builder.build());
        }
    }

    private PendingIntent buildPendingIntent(Map<String, String> data, String actionId, int notificationId) {
        Intent intent = new Intent(this, MainActivity.class);
        intent.setAction(ACTION_TRAKLO_PUSH);
        intent.putExtra("action_id", actionId);
        intent.putExtra("notification_id", notificationId);

        for (Map.Entry<String, String> entry : data.entrySet()) {
            intent.putExtra(entry.getKey(), entry.getValue());
        }

        intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);

        int flags = PendingIntent.FLAG_UPDATE_CURRENT;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            flags |= PendingIntent.FLAG_IMMUTABLE;
        }

        return PendingIntent.getActivity(this, (actionId + notificationId).hashCode(), intent, flags);
    }

    private int resolveNotificationId(Map<String, String> data, String messageId) {
        String conversationId = data.get("conversation_id");
        if (conversationId != null && !conversationId.isEmpty()) {
            return ("conversation:" + conversationId).hashCode();
        }

        String orderId = data.get("order_id");
        if (orderId != null && !orderId.isEmpty()) {
            return ("order:" + orderId).hashCode();
        }

        if (messageId != null && !messageId.isEmpty()) {
            return messageId.hashCode();
        }

        return (int) (System.currentTimeMillis() & 0x7fffffff);
    }

    private String defaultActionLabel(String kind) {
        if ("chat_message".equals(kind)) {
            return "Прочитать";
        }

        return "Открыть";
    }

    private String firstNonEmpty(String... values) {
        for (String value : values) {
            if (value != null && !value.trim().isEmpty()) {
                return value;
            }
        }

        return "";
    }

    static JSONObject buildPushPayloadFromIntent(Intent intent) {
        JSONObject payload = new JSONObject();

        if (intent == null || !ACTION_TRAKLO_PUSH.equals(intent.getAction())) {
            return payload;
        }

        try {
            payload.put("action_id", intent.getStringExtra("action_id"));

            JSONObject data = new JSONObject();
            for (String key : intent.getExtras().keySet()) {
                if ("action_id".equals(key) || "notification_id".equals(key)) {
                    continue;
                }

                Object value = intent.getExtras().get(key);
                if (value != null) {
                    data.put(key, String.valueOf(value));
                }
            }

            payload.put("data", data);
        } catch (Exception ignored) {
            return new JSONObject();
        }

        return payload;
    }
}
