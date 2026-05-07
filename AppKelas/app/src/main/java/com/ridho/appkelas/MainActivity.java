package com.ridho.appkelas;

import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Bundle;
import android.view.MenuItem;

import androidx.annotation.NonNull;
import androidx.appcompat.app.ActionBarDrawerToggle;
import androidx.appcompat.app.AppCompatActivity;
import androidx.appcompat.app.AppCompatDelegate;
import androidx.appcompat.widget.Toolbar;
import androidx.core.view.GravityCompat;
import androidx.drawerlayout.widget.DrawerLayout;
import androidx.fragment.app.Fragment;

import com.google.android.material.floatingactionbutton.FloatingActionButton;
import com.google.android.material.navigation.NavigationView;

/**
 * MainActivity.java
 * Activity utama dengan Navigation Drawer (Sidebar).
 * Menggunakan Fragment untuk konten: TaskFragment & ScheduleFragment.
 * Mendukung toggle dark/light mode.
 */
public class MainActivity extends AppCompatActivity
        implements NavigationView.OnNavigationItemSelectedListener {

    private DrawerLayout drawerLayout;
    private NavigationView navigationView;
    private Toolbar toolbar;
    private FloatingActionButton fabChat;

    private static final String PREFS_NAME = "AppKelasPrefs";
    private static final String KEY_DARK_MODE = "dark_mode";

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        // Terapkan theme sebelum setContentView
        applySavedTheme();

        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        // ==========================================
        // 1. Setup Toolbar
        // ==========================================
        toolbar = findViewById(R.id.toolbar);
        setSupportActionBar(toolbar);

        // ==========================================
        // 2. Setup Drawer Layout + Toggle (Hamburger)
        // ==========================================
        drawerLayout = findViewById(R.id.drawer_layout);
        ActionBarDrawerToggle toggle = new ActionBarDrawerToggle(
                this, drawerLayout, toolbar,
                R.string.navigation_drawer_open,
                R.string.navigation_drawer_close
        );
        drawerLayout.addDrawerListener(toggle);
        toggle.syncState();

        // Bikin icon hamburger warna putih
        toggle.getDrawerArrowDrawable().setColor(getResources().getColor(R.color.white));

        // ==========================================
        // 3. Setup Navigation View (Sidebar)
        // ==========================================
        navigationView = findViewById(R.id.nav_view);
        navigationView.setNavigationItemSelectedListener(this);

        // Update label toggle theme sesuai mode aktif
        updateThemeMenuLabel();

        // ==========================================
        // 4. Setup FAB Chat
        // ==========================================
        fabChat = findViewById(R.id.fab_chat);
        fabChat.setOnClickListener(v -> {
            Intent intent = new Intent(MainActivity.this, ChatActivity.class);
            startActivity(intent);
        });

        // ==========================================
        // 5. Load Default Fragment (Tugas)
        // ==========================================
        if (savedInstanceState == null) {
            loadFragment(new TaskFragment());
            if (getSupportActionBar() != null) {
                getSupportActionBar().setTitle("Daftar Tugas");
            }
            navigationView.setCheckedItem(R.id.nav_task);
        }
    }

    // ==========================================
    // Handle Navigasi Menu Sidebar
    // ==========================================
    @Override
    public boolean onNavigationItemSelected(@NonNull MenuItem item) {
        int itemId = item.getItemId();

        if (itemId == R.id.nav_task) {
            loadFragment(new TaskFragment());
            if (getSupportActionBar() != null) getSupportActionBar().setTitle("Daftar Tugas");

        } else if (itemId == R.id.nav_schedule) {
            loadFragment(new ScheduleFragment());
            if (getSupportActionBar() != null) getSupportActionBar().setTitle("Jadwal Pelajaran");

        } else if (itemId == R.id.nav_material) {
            loadFragment(new MaterialFragment());
            if (getSupportActionBar() != null) getSupportActionBar().setTitle("Materi Pelajaran");

        } else if (itemId == R.id.nav_toggle_theme) {
            toggleDarkMode();
            return true; // Jangan tutup drawer langsung, biar recreate dulu
        }

        drawerLayout.closeDrawer(GravityCompat.START);
        return true;
    }

    // ==========================================
    // Dark/Light Mode Logic
    // ==========================================

    private void applySavedTheme() {
        SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        boolean isDark = prefs.getBoolean(KEY_DARK_MODE, false);
        AppCompatDelegate.setDefaultNightMode(
                isDark ? AppCompatDelegate.MODE_NIGHT_YES : AppCompatDelegate.MODE_NIGHT_NO
        );
    }

    private void toggleDarkMode() {
        SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        boolean isDark = prefs.getBoolean(KEY_DARK_MODE, false);

        // Flip it
        boolean newMode = !isDark;
        prefs.edit().putBoolean(KEY_DARK_MODE, newMode).apply();

        // Apply
        AppCompatDelegate.setDefaultNightMode(
                newMode ? AppCompatDelegate.MODE_NIGHT_YES : AppCompatDelegate.MODE_NIGHT_NO
        );
        // Activity akan otomatis recreate
    }

    private void updateThemeMenuLabel() {
        SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        boolean isDark = prefs.getBoolean(KEY_DARK_MODE, false);

        MenuItem themeItem = navigationView.getMenu().findItem(R.id.nav_toggle_theme);
        if (themeItem != null) {
            themeItem.setTitle(isDark ? "☀️ Mode Terang" : "🌙 Mode Gelap");
        }
    }

    // ==========================================
    // Helper: Load Fragment ke Container
    // ==========================================
    private void loadFragment(Fragment fragment) {
        getSupportFragmentManager()
                .beginTransaction()
                .replace(R.id.fragment_container, fragment)
                .commit();
    }

    // ==========================================
    // Handle Back Button (tutup drawer dulu)
    // ==========================================
    @Override
    public void onBackPressed() {
        if (drawerLayout.isDrawerOpen(GravityCompat.START)) {
            drawerLayout.closeDrawer(GravityCompat.START);
        } else {
            super.onBackPressed();
        }
    }
}
