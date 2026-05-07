package com.ridho.appkelas;

import android.app.AlertDialog;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;
import androidx.recyclerview.widget.RecyclerView;

import com.ridho.appkelas.models.Task;

import java.util.List;

public class TaskAdapter extends RecyclerView.Adapter<TaskAdapter.TaskViewHolder> {

    private List<Task> taskList;
    private OnTaskActionListener listener;

    /**
     * Interface callback buat handle aksi dari card tugas.
     */
    public interface OnTaskActionListener {
        void onMarkComplete(Task task, int position);
        void onDelete(Task task, int position);
    }

    public TaskAdapter(List<Task> taskList) {
        this.taskList = taskList;
    }

    /**
     * Set listener dari Activity/Fragment.
     */
    public void setOnTaskActionListener(OnTaskActionListener listener) {
        this.listener = listener;
    }

    @NonNull
    @Override
    public TaskViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(parent.getContext()).inflate(R.layout.item_task, parent, false);
        return new TaskViewHolder(view);
    }

    @Override
    public void onBindViewHolder(@NonNull TaskViewHolder holder, int position) {
        Task task = taskList.get(position);
        holder.tvTitle.setText(task.getTitle());
        holder.tvSubject.setText(task.getSubject());
        holder.tvDescription.setText(task.getDescription() != null ? task.getDescription() : "Tidak ada deskripsi");
        holder.tvDeadline.setText("Deadline: " + task.getDeadline());

        // Status Badge styling
        if ("completed".equals(task.getStatus())) {
            holder.tvStatus.setText("✅ Selesai");
            holder.tvStatus.setTextColor(ContextCompat.getColor(holder.itemView.getContext(), R.color.status_completed));
            holder.tvStatus.setBackgroundResource(R.drawable.bg_badge_completed);

            // Completed visual: slightly faded title
            holder.tvTitle.setAlpha(0.6f);
        } else {
            holder.tvStatus.setText("⏳ Pending");
            holder.tvStatus.setTextColor(ContextCompat.getColor(holder.itemView.getContext(), R.color.status_pending));
            holder.tvStatus.setBackgroundResource(R.drawable.bg_badge_pending);

            holder.tvTitle.setAlpha(1.0f);
        }

        // Card Diklik -> Pop-up Detail + Tombol Aksi
        holder.itemView.setOnClickListener(v -> {
            String statusLabel = "completed".equals(task.getStatus()) ? "✅ Selesai" : "⏳ Pending";

            AlertDialog dialog = new AlertDialog.Builder(v.getContext())
                    .setTitle(task.getTitle())
                    .setMessage("📚 Mapel: " + task.getSubject() + "\n\n" +
                            "📝 Deskripsi:\n" + (task.getDescription() != null ? task.getDescription() : "-") + "\n\n" +
                            "🗓️ Deadline: " + task.getDeadline() + "\n" +
                            "📊 Status: " + statusLabel)
                    .setPositiveButton("Tutup", (d, which) -> d.dismiss())
                    .setNegativeButton("✅ Selesai", (d, which) -> {
                        if (listener != null) {
                            listener.onMarkComplete(task, holder.getAdapterPosition());
                        }
                    })
                    .setNeutralButton("🗑️ Hapus", null)
                    .create();

            dialog.show();

            // Override tombol Hapus biar bisa kasih konfirmasi dulu
            dialog.getButton(AlertDialog.BUTTON_NEUTRAL).setOnClickListener(view -> {
                new AlertDialog.Builder(v.getContext())
                        .setTitle("⚠️ Konfirmasi Hapus")
                        .setMessage("Yakin mau hapus tugas \"" + task.getTitle() + "\"?\nAksi ini tidak bisa dibatalkan.")
                        .setPositiveButton("Ya, Hapus", (d2, w2) -> {
                            if (listener != null) {
                                listener.onDelete(task, holder.getAdapterPosition());
                            }
                            dialog.dismiss();
                        })
                        .setNegativeButton("Batal", null)
                        .show();
            });

            // Sembunyiin tombol Selesai kalau task udah completed
            if ("completed".equals(task.getStatus())) {
                dialog.getButton(AlertDialog.BUTTON_NEGATIVE).setVisibility(View.GONE);
            }
        });
    }

    @Override
    public int getItemCount() {
        return taskList != null ? taskList.size() : 0;
    }

    public void updateData(List<Task> newTasks) {
        this.taskList = newTasks;
        notifyDataSetChanged();
    }

    public void removeItem(int position) {
        if (position >= 0 && position < taskList.size()) {
            taskList.remove(position);
            notifyItemRemoved(position);
            notifyItemRangeChanged(position, taskList.size());
        }
    }

    public void markItemComplete(int position) {
        if (position >= 0 && position < taskList.size()) {
            taskList.get(position).setStatus("completed");
            notifyItemChanged(position);
        }
    }

    public static class TaskViewHolder extends RecyclerView.ViewHolder {
        TextView tvTitle, tvSubject, tvDeadline, tvDescription, tvStatus;

        public TaskViewHolder(@NonNull View itemView) {
            super(itemView);
            tvTitle = itemView.findViewById(R.id.tv_task_title);
            tvSubject = itemView.findViewById(R.id.tv_task_subject);
            tvDescription = itemView.findViewById(R.id.tv_task_description);
            tvDeadline = itemView.findViewById(R.id.tv_task_deadline);
            tvStatus = itemView.findViewById(R.id.tv_task_status);
        }
    }
}
