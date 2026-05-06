package com.ridho.appkelas;

import android.os.Bundle;

import androidx.activity.EdgeToEdge;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;

import android.content.Intent;
import android.widget.TextView;
import android.util.Log;
import android.widget.Toast;

import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;

import com.google.android.material.floatingactionbutton.FloatingActionButton;
import com.google.gson.JsonObject;
import com.ridho.appkelas.models.Task;
import com.ridho.appkelas.models.TaskResponse;

import java.util.ArrayList;
import java.util.List;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class MainActivity extends AppCompatActivity implements TaskAdapter.OnTaskActionListener {

    private static final String TAG = "MainActivityAPI";
    private RecyclerView rvTasks;
    private TaskAdapter taskAdapter;
    private FloatingActionButton fabChat;
    private SwipeRefreshLayout swipeRefreshLayout;
    private ApiInterface apiInterface;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        EdgeToEdge.enable(this);
        setContentView(R.layout.activity_main);
        ViewCompat.setOnApplyWindowInsetsListener(findViewById(R.id.main), (v, insets) -> {
            Insets systemBars = insets.getInsets(WindowInsetsCompat.Type.systemBars());
            v.setPadding(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom);
            return insets;
        });

        // Inisialisasi API client sekali aja
        apiInterface = ApiClient.getClient().create(ApiInterface.class);

        // Inisialisasi RecyclerView
        rvTasks = findViewById(R.id.rv_tasks);
        rvTasks.setLayoutManager(new LinearLayoutManager(this));

        taskAdapter = new TaskAdapter(new ArrayList<>());
        taskAdapter.setOnTaskActionListener(this); // Set listener
        rvTasks.setAdapter(taskAdapter);

        // Inisialisasi SwipeRefreshLayout
        swipeRefreshLayout = findViewById(R.id.swipe_refresh);
        swipeRefreshLayout.setOnRefreshListener(this::fetchTasks);

        // Inisialisasi FAB Chat
        fabChat = findViewById(R.id.fab_chat);
        fabChat.setOnClickListener(v -> {
            Intent intent = new Intent(MainActivity.this, ChatActivity.class);
            startActivity(intent);
        });

        // Panggil API Tasks
        fetchTasks();
    }

    @Override
    protected void onResume() {
        super.onResume();
        // Refresh otomatis tiap kali user balik ke dashboard
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
                if (response.isSuccessful()) {
                    taskAdapter.markItemComplete(position);
                    Toast.makeText(MainActivity.this, "✅ Tugas ditandai selesai!", Toast.LENGTH_SHORT).show();
                    Log.d(TAG, "Task " + task.getId() + " marked complete");
                } else {
                    Toast.makeText(MainActivity.this, "Gagal update: " + response.code(), Toast.LENGTH_SHORT).show();
                    Log.e(TAG, "Update failed: " + response.code());
                }
            }

            @Override
            public void onFailure(Call<JsonObject> call, Throwable t) {
                Toast.makeText(MainActivity.this, "Error: " + t.getMessage(), Toast.LENGTH_SHORT).show();
                Log.e(TAG, "Update error: " + t.getMessage());
            }
        });
    }

    @Override
    public void onDelete(Task task, int position) {
        apiInterface.deleteTask(task.getId()).enqueue(new Callback<JsonObject>() {
            @Override
            public void onResponse(Call<JsonObject> call, Response<JsonObject> response) {
                if (response.isSuccessful()) {
                    taskAdapter.removeItem(position);
                    Toast.makeText(MainActivity.this, "🗑️ Tugas berhasil dihapus!", Toast.LENGTH_SHORT).show();
                    Log.d(TAG, "Task " + task.getId() + " deleted");
                } else {
                    Toast.makeText(MainActivity.this, "Gagal hapus: " + response.code(), Toast.LENGTH_SHORT).show();
                    Log.e(TAG, "Delete failed: " + response.code());
                }
            }

            @Override
            public void onFailure(Call<JsonObject> call, Throwable t) {
                Toast.makeText(MainActivity.this, "Error: " + t.getMessage(), Toast.LENGTH_SHORT).show();
                Log.e(TAG, "Delete error: " + t.getMessage());
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
                if (swipeRefreshLayout != null) swipeRefreshLayout.setRefreshing(false);
                
                if (response.isSuccessful() && response.body() != null) {
                    List<Task> tasks = response.body().getData();
                    Log.d(TAG, "Jumlah Tugas: " + tasks.size());

                    // Update data ke adapter
                    taskAdapter.updateData(tasks);
                } else {
                    Log.e(TAG, "Response Gagal: " + response.code());
                    Toast.makeText(MainActivity.this, "Gagal: " + response.code(), Toast.LENGTH_SHORT).show();
                }
            }

            @Override
            public void onFailure(Call<TaskResponse> call, Throwable t) {
                if (swipeRefreshLayout != null) swipeRefreshLayout.setRefreshing(false);
                Log.e(TAG, "Error Network: " + t.getMessage());
                Toast.makeText(MainActivity.this, "Error: " + t.getMessage(), Toast.LENGTH_SHORT).show();
            }
        });
    }
}
