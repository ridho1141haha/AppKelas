package com.ridho.appkelas;

import android.content.Context;
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
 * Sekarang menggunakan SessionManager untuk mengambil Token secara dinamis.
 */
public class ApiClient {

    private static final String BASE_URL = "http://192.168.137.1:8080/api/"; 
    private static Retrofit retrofit = null;

    public static Retrofit getClient(Context context) {
        if (retrofit == null) {
            HttpLoggingInterceptor logging = new HttpLoggingInterceptor();
            logging.setLevel(HttpLoggingInterceptor.Level.BODY);

            SessionManager sessionManager = new SessionManager(context);

            OkHttpClient client = new OkHttpClient.Builder()
                    .addInterceptor(logging)
                    .addInterceptor(chain -> {
                        Request original = chain.request();
                        Request.Builder requestBuilder = original.newBuilder()
                                .header("Accept", "application/json");

                        String token = sessionManager.getAuthToken();
                        if (token != null) {
                            requestBuilder.header("Authorization", "Bearer " + token);
                        }

                        return chain.proceed(requestBuilder.build());
                    })
                    .build();

            retrofit = new Retrofit.Builder()
                    .baseUrl(BASE_URL)
                    .addConverterFactory(GsonConverterFactory.create(new GsonBuilder().setLenient().create()))
                    .client(client)
                    .build();
        }
        return retrofit;
    }

    public static Retrofit getClient() {
        // Warning: This might return null if getClient(Context) hasn't been called.
        return retrofit;
    }
}
