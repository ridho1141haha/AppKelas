package com.ridho.appkelas.models;

import java.util.List;

public class ScheduleResponse {
    private boolean success;
    private String message;
    private List<Schedule> data;

    public boolean isSuccess() { return success; }
    public void setSuccess(boolean success) { this.success = success; }

    public String getMessage() { return message; }
    public void setMessage(String message) { this.message = message; }

    public List<Schedule> getData() { return data; }
    public void setData(List<Schedule> data) { this.data = data; }
}
