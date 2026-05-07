package com.ridho.appkelas;

import android.os.Bundle;
import android.util.Log;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;

import com.google.gson.JsonObject;
import com.ridho.appkelas.models.Task;
import com.ridho.appkelas.models.TaskResponse;

import java.util.ArrayList;
import java.util.List;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * TaskFragment.java
 * Fragment yang menampilkan daftar tugas dari API.
 * Logika dipindahkan dari MainActivity lama.
 */
public class TaskFragment extends Fragment implements TaskAdapter.OnTaskActionListener {

    private static final String TAG = "TaskFragment";
    private RecyclerView rvTasks;
    private TaskAdapter taskAdapter;
    private SwipeRefreshLayout swipeRefreshLayout;
    private ApiInterface apiInterface;

    public TaskFragment() {
        // Required empty public constructor
    }

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container,
                             @Nullable Bundle savedInstanceState) {
        return inflater.inflate(R.layout.fragment_task, container, false);
    }

    @Override
    public void onViewCreated(@NonNull View view, @Nullable Bundle savedInstanceState) {
        super.onViewCreated(view, savedInstanceState);

        // Inisialisasi API client
        apiInterface = ApiClient.getClient(requireContext()).create(ApiInterface.class);

        // Setup RecyclerView
        rvTasks = view.findViewById(R.id.rv_tasks);
        rvTasks.setLayoutManager(new LinearLayoutManager(requireContext()));

        taskAdapter = new TaskAdapter(new ArrayList<>());
        taskAdapter.setOnTaskActionListener(this);
        rvTasks.setAdapter(taskAdapter);

        // Setup SwipeRefresh
        swipeRefreshLayout = view.findViewById(R.id.swipe_refresh);
        swipeRefreshLayout.setColorSchemeResources(R.color.primary, R.color.secondary);
        swipeRefreshLayout.setOnRefreshListener(this::fetchTasks);

        // Load data
        fetchTasks();
    }

    @Override
    public void onResume() {
        super.onResume();
        fetchTasks();
    }

    // ==========================================
    // Callback dari TaskAdapter
    // ==========================================

    @Override
    public void onMarkComplete(Task task, int position) {
        apiInterface.updateTask(task.getId(), "completed").enqueue(new Callback<JsonObject>() {
            @Override
            public void onResponse(Call<JsonObject> call, Response<JsonObject> response) {
                if (!isAdded()) return;
                if (response.isSuccessful()) {
                    taskAdapter.markItemComplete(position);
                    Toast.makeText(requireContext(), "✅ Tugas ditandai selesai!", Toast.LENGTH_SHORT).show();
                } else {
                    Toast.makeText(requireContext(), "Gagal update: " + response.code(), Toast.LENGTH_SHORT).show();
                }
            }

            @Override
            public void onFailure(Call<JsonObject> call, Throwable t) {
                if (!isAdded()) return;
                Toast.makeText(requireContext(), "Error: " + t.getMessage(), Toast.LENGTH_SHORT).show();
            }
        });
    }

    @Override
    public void onDelete(Task task, int position) {
        apiInterface.deleteTask(task.getId()).enqueue(new Callback<JsonObject>() {
            @Override
            public void onResponse(Call<JsonObject> call, Response<JsonObject> response) {
                if (!isAdded()) return;
                if (response.isSuccessful()) {
                    taskAdapter.removeItem(position);
                    Toast.makeText(requireContext(), "🗑️ Tugas berhasil dihapus!", Toast.LENGTH_SHORT).show();
                } else {
                    Toast.makeText(requireContext(), "Gagal hapus: " + response.code(), Toast.LENGTH_SHORT).show();
                }
            }

            @Override
            public void onFailure(Call<JsonObject> call, Throwable t) {
                if (!isAdded()) return;
                Toast.makeText(requireContext(), "Error: " + t.getMessage(), Toast.LENGTH_SHORT).show();
            }
        });
    }

    // ==========================================
    // Fetch Tasks dari API
    // ==========================================

    private void fetchTasks() {
        apiInterface.getTasks().enqueue(new Callback<TaskResponse>() {
            @Override
            public void onResponse(Call<TaskResponse> call, Response<TaskResponse> response) {
                if (!isAdded()) return;
                if (swipeRefreshLayout != null) swipeRefreshLayout.setRefreshing(false);

                if (response.isSuccessful() && response.body() != null) {
                    List<Task> tasks = response.body().getData();
                    Log.d(TAG, "Jumlah Tugas: " + tasks.size());
                    taskAdapter.updateData(tasks);
                } else {
                    Log.e(TAG, "Response Gagal: " + response.code());
                    Toast.makeText(requireContext(), "Gagal: " + response.code(), Toast.LENGTH_SHORT).show();
                }
            }

            @Override
            public void onFailure(Call<TaskResponse> call, Throwable t) {
                if (!isAdded()) return;
                if (swipeRefreshLayout != null) swipeRefreshLayout.setRefreshing(false);
                Log.e(TAG, "Error Network: " + t.getMessage());
                Toast.makeText(requireContext(), "Error: " + t.getMessage(), Toast.LENGTH_SHORT).show();
            }
        });
    }
}
