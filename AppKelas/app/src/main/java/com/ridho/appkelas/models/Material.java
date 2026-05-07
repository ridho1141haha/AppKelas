package com.ridho.appkelas.models;

import com.google.gson.annotations.SerializedName;

public class Material {
    @SerializedName("id")
    private int id;

    @SerializedName("title")
    private String title;

    @SerializedName("subject")
    private String subject;

    @SerializedName("description")
    private String description;

    @SerializedName("file_url")
    private String fileUrl;

    @SerializedName("created_at")
    private String createdAt;

    // Getters
    public int getId() { return id; }
    public String getTitle() { return title; }
    public String getSubject() { return subject; }
    public String getDescription() { return description; }
    public String getFileUrl() { return fileUrl; }
    public String getCreatedAt() { return createdAt; }
}
