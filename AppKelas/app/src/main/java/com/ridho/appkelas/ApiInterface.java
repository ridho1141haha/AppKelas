package com.ridho.appkelas;

import com.ridho.appkelas.models.MaterialResponse;
import com.ridho.appkelas.models.ScheduleResponse;
import com.ridho.appkelas.models.TaskResponse;

import okhttp3.MultipartBody;
import okhttp3.RequestBody;
import retrofit2.Call;
import retrofit2.http.DELETE;
import retrofit2.http.Field;
import retrofit2.http.FormUrlEncoded;
import retrofit2.http.GET;
import retrofit2.http.Multipart;
import retrofit2.http.PUT;
import retrofit2.http.POST;
import retrofit2.http.Part;
import retrofit2.http.Path;

/**
 * ApiInterface.java
 * Interface untuk mendefinisikan endpoint API Laravel.
 */
public interface ApiInterface {

    /**
     * Endpoint Login
     */
    @POST("login")
    @FormUrlEncoded
    Call<com.google.gson.JsonObject> login(
            @Field("email") String email,
            @Field("password") String password
    );

    /**
     * Mengambil daftar tugas (Tasks)
     */
    @GET("tasks")
    Call<TaskResponse> getTasks();

    /**
     * Update status tugas
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
     * Mengambil daftar jadwal (Schedules)
     */
    @GET("schedules")
    Call<ScheduleResponse> getSchedules();

    /**
     * Mengambil daftar materi (Materials)
     */
    @GET("materials")
    Call<MaterialResponse> getMaterials();

    /**
     * Upload File Materi
     */
    @Multipart
    @POST("materials/upload")
    Call<com.google.gson.JsonObject> uploadFile(
            @Part MultipartBody.Part file
    );

    /**
     * Simpan data materi ke database (setelah file diupload)
     */
    @POST("materials")
    @FormUrlEncoded
    Call<com.google.gson.JsonObject> saveMaterial(
            @Field("title") String title,
            @Field("subject") String subject,
            @Field("description") String description,
            @Field("file_url") String fileUrl
    );

    /**
     * Chat dengan AI
     */
    @POST("chat")
    @FormUrlEncoded
    Call<com.google.gson.JsonObject> sendMessage(@Field("message") String message);

}
