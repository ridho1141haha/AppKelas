package com.ridho.appkelas;

import android.content.Intent;
import android.os.Bundle;
import android.util.Log;
import android.view.View;
import android.widget.ProgressBar;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

import com.google.android.material.button.MaterialButton;
import com.google.android.material.textfield.TextInputEditText;
import com.google.gson.JsonObject;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class LoginActivity extends AppCompatActivity {

    private static final String TAG = "LoginActivity";
    private TextInputEditText etEmail, etPassword;
    private MaterialButton btnLogin;
    private ProgressBar pbLoading;
    private SessionManager sessionManager;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        
        sessionManager = new SessionManager(this);

        // CEK APAKAH SUDAH LOGIN?
        if (sessionManager.isLoggedIn()) {
            moveToMainActivity();
            return;
        }

        setContentView(R.layout.activity_login);

        etEmail = findViewById(R.id.et_email);
        etPassword = findViewById(R.id.et_password);
        btnLogin = findViewById(R.id.btn_login);
        pbLoading = findViewById(R.id.pb_loading);

        btnLogin.setOnClickListener(v -> {
            String email = etEmail.getText().toString().trim();
            String password = etPassword.getText().toString().trim();

            if (email.isEmpty() || password.isEmpty()) {
                Toast.makeText(this, "Email dan Password wajib diisi!", Toast.LENGTH_SHORT).show();
            } else {
                performLogin(email, password);
            }
        });
    }

    private void performLogin(String email, String password) {
        setLoading(true);

        ApiInterface apiInterface = ApiClient.getClient(this).create(ApiInterface.class);
        
        // Panggil endpoint login
        // Laravel biasanya butuh email & password
        JsonObject loginData = new JsonObject();
        loginData.addProperty("email", email);
        loginData.addProperty("password", password);

        // Catatan: Di ApiInterface.java kita butuh endpoint POST /login
        // Jika belum ada, kita bisa pake call manual atau update ApiInterface nanti.
        // Asumsi kita butuh form encoded atau JSON body.
        
        // Gw pake sendMessage sebagai template sementara kalau belum ada route login spesifik di interface, 
        // tapi sebaiknya kita pake route login beneran.
        
        // Update: Mari kita asumsikan ApiInterface sudah punya @POST("login")
        // Saya akan tambahkan itu di step berikutnya.
        
        apiInterface.login(email, password).enqueue(new Callback<JsonObject>() {
            @Override
            public void onResponse(Call<JsonObject> call, Response<JsonObject> response) {
                setLoading(false);
                if (response.isSuccessful() && response.body() != null) {
                    try {
                        com.google.gson.JsonObject responseBody = response.body();
                        com.google.gson.JsonObject data = responseBody.getAsJsonObject("data");

                        String token = data.get("access_token").getAsString();
                        String name = data.getAsJsonObject("user").get("name").getAsString();

                        // SIMPAN SESSION
                        sessionManager.saveAuthToken(token);
                        sessionManager.saveUserName(name);

                        Toast.makeText(LoginActivity.this, "Selamat datang, " + name + "!", Toast.LENGTH_SHORT).show();
                        moveToMainActivity();
                    } catch (Exception e) {
                        Log.e(TAG, "Parsing error: " + e.getMessage());
                        Toast.makeText(LoginActivity.this, "Gagal memproses data server.", Toast.LENGTH_SHORT).show();
                    }
                } else {

                    Toast.makeText(LoginActivity.this, "Login Gagal. Cek email/password lu.", Toast.LENGTH_SHORT).show();
                }
            }

            @Override
            public void onFailure(Call<JsonObject> call, Throwable t) {
                setLoading(false);
                Toast.makeText(LoginActivity.this, "Error Network: " + t.getMessage(), Toast.LENGTH_SHORT).show();
            }
        });
    }

    private void setLoading(boolean isLoading) {
        pbLoading.setVisibility(isLoading ? View.VISIBLE : View.GONE);
        btnLogin.setEnabled(!isLoading);
        etEmail.setEnabled(!isLoading);
        etPassword.setEnabled(!isLoading);
    }

    private void moveToMainActivity() {
        Intent intent = new Intent(LoginActivity.this, MainActivity.class);
        startActivity(intent);
        finish(); // Biar gak bisa back ke login lagi
    }
}
