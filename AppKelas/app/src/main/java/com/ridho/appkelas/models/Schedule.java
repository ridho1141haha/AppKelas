package com.ridho.appkelas.models;

import com.google.gson.annotations.SerializedName;

public class Schedule {
    @SerializedName("id")
    private int id;

    @SerializedName("day")
    private String day;

    @SerializedName("type")
    private String type;

    @SerializedName("subjects")
    private String subjects;

    @SerializedName("dismissal_time")
    private String dismissalTime;

    // Getters
    public int getId() { return id; }
    public String getDay() { return day; }
    public String getType() { return type; }
    public String getSubjects() { return subjects; }
    public String getDismissalTime() { return dismissalTime; }

    // Setters
    public void setId(int id) { this.id = id; }
    public void setDay(String day) { this.day = day; }
    public void setType(String type) { this.type = type; }
    public void setSubjects(String subjects) { this.subjects = subjects; }
    public void setDismissalTime(String dismissalTime) { this.dismissalTime = dismissalTime; }
}
