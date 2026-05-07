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

import com.google.android.material.chip.ChipGroup;
import com.ridho.appkelas.models.Schedule;
import com.ridho.appkelas.models.ScheduleResponse;

import java.util.ArrayList;
import java.util.List;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * ScheduleFragment.java
 * Fragment yang menampilkan daftar jadwal pelajaran dari API.
 * Mendukung filter kategori secara lokal.
 */
public class ScheduleFragment extends Fragment {

    private static final String TAG = "ScheduleFragment";
    private RecyclerView rvSchedules;
    private ScheduleAdapter scheduleAdapter;
    private SwipeRefreshLayout swipeRefreshLayout;
    private ChipGroup chipGroupCategory;
    private ApiInterface apiInterface;

    private List<Schedule> allSchedules = new ArrayList<>();

    public ScheduleFragment() {
        // Required empty public constructor
    }

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container,
                             @Nullable Bundle savedInstanceState) {
        return inflater.inflate(R.layout.fragment_schedule, container, false);
    }

    @Override
    public void onViewCreated(@NonNull View view, @Nullable Bundle savedInstanceState) {
        super.onViewCreated(view, savedInstanceState);

        // Inisialisasi API client
        apiInterface = ApiClient.getClient(requireContext()).create(ApiInterface.class);

        // Inisialisasi View
        rvSchedules = view.findViewById(R.id.rv_schedules);
        swipeRefreshLayout = view.findViewById(R.id.swipe_refresh_schedule);
        chipGroupCategory = view.findViewById(R.id.chip_group_category);

        // Setup RecyclerView
        rvSchedules.setLayoutManager(new LinearLayoutManager(requireContext()));
        scheduleAdapter = new ScheduleAdapter(new ArrayList<>());
        rvSchedules.setAdapter(scheduleAdapter);

        // Setup SwipeRefresh
        swipeRefreshLayout.setColorSchemeResources(R.color.primary, R.color.secondary);
        swipeRefreshLayout.setOnRefreshListener(this::fetchSchedules);

        // Setup Chip Filter Listener
        chipGroupCategory.setOnCheckedChangeListener((group, checkedId) -> {
            filterData(checkedId);
        });

        // Load data
        fetchSchedules();
    }

    @Override
    public void onResume() {
        super.onResume();
        fetchSchedules();
    }

    /**
     * Mengambil data dari API
     */
    private void fetchSchedules() {
        apiInterface.getSchedules().enqueue(new Callback<ScheduleResponse>() {
            @Override
            public void onResponse(Call<ScheduleResponse> call, Response<ScheduleResponse> response) {
                if (!isAdded()) return;
                if (swipeRefreshLayout != null) swipeRefreshLayout.setRefreshing(false);

                if (response.isSuccessful() && response.body() != null) {
                    allSchedules = response.body().getData();
                    Log.d(TAG, "Jumlah Jadwal: " + allSchedules.size());
                    
                    // Setelah data ditarik, apply filter yang sedang aktif
                    filterData(chipGroupCategory.getCheckedChipId());
                } else {
                    Log.e(TAG, "Response Gagal: " + response.code());
                    Toast.makeText(requireContext(), "Gagal: " + response.code(), Toast.LENGTH_SHORT).show();
                }
            }

            @Override
            public void onFailure(Call<ScheduleResponse> call, Throwable t) {
                if (!isAdded()) return;
                if (swipeRefreshLayout != null) swipeRefreshLayout.setRefreshing(false);
                Log.e(TAG, "Error Network: " + t.getMessage());
                Toast.makeText(requireContext(), "Error: " + t.getMessage(), Toast.LENGTH_SHORT).show();
            }
        });
    }

    /**
     * Memfilter data secara lokal berdasarkan chip yang dipilih
     */
    private void filterData(int checkedId) {
        if (allSchedules == null || allSchedules.isEmpty()) return;

        List<Schedule> filteredList = new ArrayList<>();

        if (checkedId == R.id.chip_all) {
            filteredList.addAll(allSchedules);
        } else {
            String targetType = "";
            if (checkedId == R.id.chip_normatif) targetType = "Normatif - Adaptif";
            else if (checkedId == R.id.chip_m1) targetType = "Produktif - Minggu 1";
            else if (checkedId == R.id.chip_m2) targetType = "Produktif - Minggu 2";

            for (Schedule s : allSchedules) {
                if (s.getType().equalsIgnoreCase(targetType)) {
                    filteredList.add(s);
                }
            }
        }

        scheduleAdapter.updateData(filteredList);
        
        // Scroll ke atas setiap kali ganti filter
        rvSchedules.scrollToPosition(0);
    }
}
