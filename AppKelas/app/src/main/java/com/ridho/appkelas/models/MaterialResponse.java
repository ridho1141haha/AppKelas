package com.ridho.appkelas.models;

import com.google.gson.annotations.SerializedName;
import java.util.List;

public class MaterialResponse {
    @SerializedName("success")
    private boolean success;

    @SerializedName("message")
    private String message;

    @SerializedName("data")
    private List<Material> data;

    public boolean isSuccess() { return success; }
    public String getMessage() { return message; }
    public List<Material> getData() { return data; }
}
