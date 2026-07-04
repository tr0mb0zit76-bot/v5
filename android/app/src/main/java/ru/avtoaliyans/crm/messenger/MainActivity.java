package ru.avtoaliyans.crm.messenger;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.os.Build;
import android.os.Bundle;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        createNotificationChannels();
    }

    private void createNotificationChannels() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) {
            return;
        }

        NotificationManager manager = getSystemService(NotificationManager.class);
        if (manager == null) {
            return;
        }

        manager.createNotificationChannel(new NotificationChannel(
            "crm_chat_messages",
            "Чаты",
            NotificationManager.IMPORTANCE_HIGH
        ));

        manager.createNotificationChannel(new NotificationChannel(
            "crm_orders",
            "Заказы",
            NotificationManager.IMPORTANCE_HIGH
        ));

        manager.createNotificationChannel(new NotificationChannel(
            "crm_accounting",
            "Бухгалтерия",
            NotificationManager.IMPORTANCE_DEFAULT
        ));
    }
}
