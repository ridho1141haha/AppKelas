package com.ridho.appkelas.models;

import com.google.gson.annotations.SerializedName;
import java.util.List;

public class TaskResponse {
    @SerializedName("success")
    private boolean success;

    @SerializedName("message")
    private String message;

    @SerializedName("data")
    private List<Task> data;

    // Getters and Setters
    public boolean isSuccess() { return success; }
    public void setSuccess(boolean success) { this.success = success; }

    public String getMessage() { return message; }
    public void setMessage(String message) { this.message = message; }

    public List<Task> getData() { return data; }
    public void setData(List<Task> data) { this.data = data; }
}
