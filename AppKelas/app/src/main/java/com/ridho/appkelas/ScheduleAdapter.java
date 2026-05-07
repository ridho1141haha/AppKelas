package com.ridho.appkelas;

import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;

import com.ridho.appkelas.models.Schedule;

import java.util.List;

public class ScheduleAdapter extends RecyclerView.Adapter<ScheduleAdapter.ScheduleViewHolder> {

    private List<Schedule> scheduleList;

    public ScheduleAdapter(List<Schedule> scheduleList) {
        this.scheduleList = scheduleList;
    }

    @NonNull
    @Override
    public ScheduleViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(parent.getContext()).inflate(R.layout.item_schedule, parent, false);
        return new ScheduleViewHolder(view);
    }

    @Override
    public void onBindViewHolder(@NonNull ScheduleViewHolder holder, int position) {
        Schedule schedule = scheduleList.get(position);

        holder.tvDay.setText(schedule.getDay());
        holder.tvType.setText(schedule.getType());
        holder.tvSubjects.setText(schedule.getSubjects());
        holder.tvDismissal.setText("Pulang: " + schedule.getDismissalTime());
    }

    @Override
    public int getItemCount() {
        return scheduleList != null ? scheduleList.size() : 0;
    }

    public void updateData(List<Schedule> newSchedules) {
        this.scheduleList = newSchedules;
        notifyDataSetChanged();
    }

    public static class ScheduleViewHolder extends RecyclerView.ViewHolder {
        TextView tvDay, tvType, tvSubjects, tvDismissal;

        public ScheduleViewHolder(@NonNull View itemView) {
            super(itemView);
            tvDay = itemView.findViewById(R.id.tv_schedule_day);
            tvType = itemView.findViewById(R.id.tv_schedule_type);
            tvSubjects = itemView.findViewById(R.id.tv_schedule_subjects);
            tvDismissal = itemView.findViewById(R.id.tv_schedule_dismissal);
        }
    }
}
