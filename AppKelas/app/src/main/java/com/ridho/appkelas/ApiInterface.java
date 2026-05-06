package com.ridho.appkelas;

import com.ridho.appkelas.models.TaskResponse;

import retrofit2.Call;
import retrofit2.http.DELETE;
import retrofit2.http.Field;
import retrofit2.http.FormUrlEncoded;
import retrofit2.http.GET;
import retrofit2.http.PUT;
import retrofit2.http.POST;
import retrofit2.http.Path;

/**
 * ApiInterface.java
 * Interface untuk mendefinisikan endpoint API Laravel.
 */
public interface ApiInterface {

    /**
     * Mengambil daftar tugas (Tasks)
     */
    @GET("tasks")
    Call<TaskResponse> getTasks();

    /**
     * Update status tugas (misal: tandai selesai)
     */
    @PUT("tasks/{id}")
    @FormUrlEncoded
    Call<com.google.gson.JsonObject> updateTask(
            @Path("id") int id,
            @Field("status") String status
    );

    /**
     * Hapus tugas berdasarkan ID
     */
    @DELETE("tasks/{id}")
    Call<com.google.gson.JsonObject> deleteTask(@Path("id") int id);

    /**
     * Chat dengan AI
     */
    @POST("chat")
    @FormUrlEncoded
    Call<com.google.gson.JsonObject> sendMessage(@Field("message") String message);
    
}
