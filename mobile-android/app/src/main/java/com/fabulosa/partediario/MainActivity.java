package com.fabulosa.partediario;

import android.Manifest;
import android.app.Activity;
import android.content.ContentValues;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.location.Criteria;
import android.location.Location;
import android.location.LocationListener;
import android.location.LocationManager;
import android.net.Uri;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.provider.MediaStore;
import android.webkit.GeolocationPermissions;
import android.webkit.JavascriptInterface;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import java.util.ArrayList;

public class MainActivity extends Activity {
    private static final int LOCATION_PERMISSION_REQUEST = 40;
    private static final int FILE_REQUEST = 41;
    private static final int CAMERA_PERMISSION_REQUEST = 42;
    private WebView webView;
    private ValueCallback<Uri[]> fileCallback;
    private Uri cameraUri;
    private String geoOrigin;
    private GeolocationPermissions.Callback geoCallback;
    private LocationManager locationManager;
    private LocationListener locationListener;
    private Location fallbackLocation;
    private final Handler handler = new Handler(Looper.getMainLooper());

    @Override protected void onCreate(Bundle state) {
        super.onCreate(state);
        webView = new WebView(this);
        setContentView(webView);
        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setGeolocationEnabled(true);
        settings.setAllowFileAccess(true);
        settings.setMediaPlaybackRequiresUserGesture(false);
        webView.addJavascriptInterface(new LocationBridge(), "AndroidLocation");
        webView.setWebViewClient(new WebViewClient() {
            @Override public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                Uri uri = request.getUrl();
                Uri server = Uri.parse(BuildConfig.SERVER_URL);
                boolean trusted = server.getHost() != null && server.getHost().equalsIgnoreCase(uri.getHost())
                    && server.getPort() == uri.getPort();
                if (trusted) return false;
                startActivity(new Intent(Intent.ACTION_VIEW, uri));
                return true;
            }
        });
        webView.setWebChromeClient(new WebChromeClient() {
            @Override public void onGeolocationPermissionsShowPrompt(String origin, GeolocationPermissions.Callback callback) {
                if (checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED) callback.invoke(origin, true, false);
                else {
                    geoOrigin = origin;
                    geoCallback = callback;
                    requestPermissions(new String[]{Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION}, LOCATION_PERMISSION_REQUEST);
                }
            }

            @Override public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> callback, FileChooserParams params) {
                if (fileCallback != null) fileCallback.onReceiveValue(null);
                fileCallback = callback;
                Intent gallery = new Intent(Intent.ACTION_GET_CONTENT).setType("image/*").addCategory(Intent.CATEGORY_OPENABLE);
                ArrayList<Intent> extras = new ArrayList<>();
                if (checkSelfPermission(Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) {
                    ContentValues values = new ContentValues();
                    values.put(MediaStore.Images.Media.DISPLAY_NAME, "parte_diario_" + System.currentTimeMillis() + ".jpg");
                    cameraUri = getContentResolver().insert(MediaStore.Images.Media.EXTERNAL_CONTENT_URI, values);
                    Intent camera = new Intent(MediaStore.ACTION_IMAGE_CAPTURE);
                    camera.putExtra(MediaStore.EXTRA_OUTPUT, cameraUri);
                    extras.add(camera);
                } else requestPermissions(new String[]{Manifest.permission.CAMERA}, CAMERA_PERMISSION_REQUEST);
                Intent chooser = Intent.createChooser(gallery, "Foto o archivo");
                chooser.putExtra(Intent.EXTRA_INITIAL_INTENTS, extras.toArray(new Intent[0]));
                startActivityForResult(chooser, FILE_REQUEST);
                return true;
            }
        });
        webView.loadUrl(BuildConfig.SERVER_URL);
    }

    public class LocationBridge {
        @JavascriptInterface public void requestLocation() {
            runOnUiThread(() -> requestNativeLocation());
        }
    }

    private void requestNativeLocation() {
        if (checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION) != PackageManager.PERMISSION_GRANTED
            && checkSelfPermission(Manifest.permission.ACCESS_COARSE_LOCATION) != PackageManager.PERMISSION_GRANTED) {
            requestPermissions(new String[]{Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION}, LOCATION_PERMISSION_REQUEST);
            return;
        }
        locationManager = (LocationManager) getSystemService(LOCATION_SERVICE);
        Criteria criteria = new Criteria();
        criteria.setAccuracy(Criteria.ACCURACY_FINE);
        String provider = locationManager.getBestProvider(criteria, true);
        if (provider == null) {
            deliverLocationError("Activa la ubicación del teléfono e inténtalo nuevamente.");
            return;
        }
        try {
            fallbackLocation = locationManager.getLastKnownLocation(provider);
            if (locationListener != null) locationManager.removeUpdates(locationListener);
            locationListener = new LocationListener() {
                @Override public void onLocationChanged(Location location) {
                    deliverLocation(location);
                }
                @Override public void onStatusChanged(String changedProvider, int status, Bundle extras) {}
                @Override public void onProviderEnabled(String enabledProvider) {}
                @Override public void onProviderDisabled(String disabledProvider) {
                    deliverLocationError("El proveedor de ubicación está desactivado.");
                }
            };
            locationManager.requestLocationUpdates(provider, 0, 0, locationListener, Looper.getMainLooper());
            handler.removeCallbacksAndMessages(null);
            handler.postDelayed(() -> {
                if (locationListener == null) return;
                if (fallbackLocation != null) deliverLocation(fallbackLocation);
                else deliverLocationError("Android no pudo obtener una señal GPS. Inténtalo al aire libre.");
            }, 15000);
        } catch (SecurityException error) {
            deliverLocationError("Debes permitir la ubicación precisa para continuar.");
        }
    }

    private void deliverLocation(Location location) {
        stopLocationUpdates();
        String script = "window.setNativeLocation&&window.setNativeLocation(" + location.getLatitude() + "," + location.getLongitude() + "," + location.getAccuracy() + ")";
        webView.evaluateJavascript(script, null);
    }

    private void deliverLocationError(String message) {
        stopLocationUpdates();
        String safeMessage = message.replace("\\", "\\\\").replace("'", "\\'");
        webView.evaluateJavascript("window.setNativeLocationError&&window.setNativeLocationError('" + safeMessage + "')", null);
    }

    private void stopLocationUpdates() {
        handler.removeCallbacksAndMessages(null);
        if (locationManager != null && locationListener != null) {
            try { locationManager.removeUpdates(locationListener); } catch (SecurityException ignored) {}
        }
        locationListener = null;
    }

    @Override protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);
        if (requestCode != FILE_REQUEST || fileCallback == null) return;
        Uri[] result = null;
        if (resultCode == RESULT_OK) {
            Uri selected = data != null ? data.getData() : cameraUri;
            if (selected != null) result = new Uri[]{selected};
        }
        fileCallback.onReceiveValue(result);
        fileCallback = null;
        cameraUri = null;
    }

    @Override public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] results) {
        super.onRequestPermissionsResult(requestCode, permissions, results);
        if (requestCode == LOCATION_PERMISSION_REQUEST) {
            boolean granted = checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED
                || checkSelfPermission(Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED;
            if (geoCallback != null) {
                geoCallback.invoke(geoOrigin, granted, false);
                geoCallback = null;
                geoOrigin = null;
            }
            if (granted) requestNativeLocation();
            else deliverLocationError("Permiso de ubicación rechazado. Habilítalo en Ajustes de Android.");
        }
    }

    @Override protected void onDestroy() {
        stopLocationUpdates();
        if (webView != null) webView.destroy();
        super.onDestroy();
    }

    @Override public void onBackPressed() {
        if (webView.canGoBack()) webView.goBack(); else super.onBackPressed();
    }
}
