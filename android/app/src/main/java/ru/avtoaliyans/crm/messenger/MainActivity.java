package ru.avtoaliyans.crm.messenger;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.content.Context;
import android.content.pm.PackageInfo;
import android.content.pm.PackageManager;
import android.os.Build;
import android.os.Bundle;
import android.webkit.JavascriptInterface;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        this.bridge.getWebView().addJavascriptInterface(new TrakloAppInfo(this), "TrakloApp");
        createNotificationChannels();
    }

    public static class TrakloAppInfo {
        private final Context context;

        public TrakloAppInfo(Context context) {
            this.context = context.getApplicationContext();
        }

        @JavascriptInterface
        public int getVersionCode() {
            try {
                PackageInfo info = context.getPackageManager().getPackageInfo(context.getPackageName(), 0);

                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
                    return (int) info.getLongVersionCode();
                }

                return info.versionCode;
            } catch (PackageManager.NameNotFoundException e) {
                return 0;
            }
        }

        @JavascriptInterface
        public String getVersionName() {
            try {
                PackageInfo info = context.getPackageManager().getPackageInfo(context.getPackageName(), 0);

                return info.versionName == null ? "" : info.versionName;
            } catch (PackageManager.NameNotFoundException e) {
                return "";
            }
        }
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
