package com.ridho.appkelas;

import com.google.gson.Gson;
import com.google.gson.GsonBuilder;

import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.logging.HttpLoggingInterceptor;
import retrofit2.Retrofit;
import retrofit2.converter.gson.GsonConverterFactory;

/**
 * ApiClient.java
 * Singleton class untuk inisialisasi Retrofit.
 */
public class ApiClient {

    // GANTI IP INI dengan IP lokal laptop/PC lu (cek pake 'ipconfig' di cmd)
    // Jangan pake localhost/127.0.0.1 karena itu ngerujuk ke emulatornya sendiri.
    private static final String BASE_URL = "http://192.168.137.1:8080/api/"; 
    
    // GANTI TOKEN INI dengan token yang didapet pas login
    private static final String AUTH_TOKEN = "2|mJpWTfdTraD0nMvBe2vFVBVg10xy6SFOyHV2qNdc35e233c4";

    private static Retrofit retrofit = null;

    public static Retrofit getClient() {
        if (retrofit == null) {
            // 1. Setup Logging Interceptor (Biar keliatan di Logcat request/respon-nya)
            HttpLoggingInterceptor logging = new HttpLoggingInterceptor();
            logging.setLevel(HttpLoggingInterceptor.Level.BODY);

            // 2. Setup OkHttpClient dengan Auth Interceptor
            OkHttpClient client = new OkHttpClient.Builder()
                    .addInterceptor(logging)
                    .addInterceptor(chain -> {
                        Request original = chain.request();
                        Request request = original.newBuilder()
                                .header("Authorization", "Bearer " + AUTH_TOKEN)
                                .header("Accept", "application/json")
                                .method(original.method(), original.body())
                                .build();
                        return chain.proceed(request);
                    })
                    .build();

            // 3. Build Retrofit
            Gson gson = new GsonBuilder()
                    .setLenient()
                    .create();

            retrofit = new Retrofit.Builder()
                    .baseUrl(BASE_URL)
                    .addConverterFactory(GsonConverterFactory.create(gson))
                    .client(client)
                    .build();
        }
        return retrofit;
    }
}
