package com.ridho.appkelas;

import android.content.Context;
import android.content.SharedPreferences;

/**
 * SessionManager.java
 * Class untuk menyimpan Token Login secara persistent menggunakan SharedPreferences.
 */
public class SessionManager {
    private static final String PREF_NAME = "AppKelasAuth";
    private static final String KEY_TOKEN = "auth_token";
    private static final String KEY_USER_NAME = "user_name";
    private static final String KEY_IS_LOGGED_IN = "is_logged_in";

    private SharedPreferences pref;
    private SharedPreferences.Editor editor;
    private Context context;

    public SessionManager(Context context) {
        this.context = context;
        pref = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
        editor = pref.edit();
    }

    public void saveAuthToken(String token) {
        editor.putString(KEY_TOKEN, token);
        editor.putBoolean(KEY_IS_LOGGED_IN, true);
        editor.apply();
    }

    public void saveUserName(String name) {
        editor.putString(KEY_USER_NAME, name);
        editor.apply();
    }

    public String getAuthToken() {
        return pref.getString(KEY_TOKEN, null);
    }

    public String getUserName() {
        return pref.getString(KEY_USER_NAME, "User");
    }

    public boolean isLoggedIn() {
        return pref.getBoolean(KEY_IS_LOGGED_IN, false);
    }

    public void logout() {
        editor.clear();
        editor.apply();
    }
}
