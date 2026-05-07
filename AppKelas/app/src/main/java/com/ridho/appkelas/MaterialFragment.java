package com.ridho.appkelas;

import android.content.Intent;
import android.os.Bundle;
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

import com.google.android.material.floatingactionbutton.FloatingActionButton;
import com.ridho.appkelas.models.Material;
import com.ridho.appkelas.models.MaterialResponse;

import java.util.ArrayList;
import java.util.List;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class MaterialFragment extends Fragment {

    private RecyclerView rvMaterials;
    private MaterialAdapter materialAdapter;
    private SwipeRefreshLayout swipeRefresh;
    private FloatingActionButton fabUpload;

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        View view = inflater.inflate(R.layout.fragment_material, container, false);

        rvMaterials = view.findViewById(R.id.rv_materials);
        swipeRefresh = view.findViewById(R.id.swipe_refresh_material);
        fabUpload = view.findViewById(R.id.fab_upload_material);

        rvMaterials.setLayoutManager(new LinearLayoutManager(getContext()));
        materialAdapter = new MaterialAdapter(new ArrayList<>());
        rvMaterials.setAdapter(materialAdapter);

        swipeRefresh.setOnRefreshListener(this::fetchMaterials);

        fabUpload.setOnClickListener(v -> {
            Intent intent = new Intent(getActivity(), UploadActivity.class);
            startActivity(intent);
        });

        fetchMaterials();

        return view;
    }

    @Override
    public void onResume() {
        super.onResume();
        fetchMaterials();
    }

    private void fetchMaterials() {
        ApiInterface api = ApiClient.getClient(getContext()).create(ApiInterface.class);
        api.getMaterials().enqueue(new Callback<MaterialResponse>() {
            @Override
            public void onResponse(Call<MaterialResponse> call, Response<MaterialResponse> response) {
                if (swipeRefresh != null) swipeRefresh.setRefreshing(false);
                if (response.isSuccessful() && response.body() != null) {
                    materialAdapter.updateData(response.body().getData());
                }
            }

            @Override
            public void onFailure(Call<MaterialResponse> call, Throwable t) {
                if (swipeRefresh != null) swipeRefresh.setRefreshing(false);
                Toast.makeText(getContext(), "Gagal ambil materi: " + t.getMessage(), Toast.LENGTH_SHORT).show();
            }
        });
    }
}
