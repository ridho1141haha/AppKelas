package com.ridho.appkelas;

import android.content.Intent;
import android.net.Uri;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.Button;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;

import com.ridho.appkelas.models.Material;

import java.util.List;

public class MaterialAdapter extends RecyclerView.Adapter<MaterialAdapter.MaterialViewHolder> {

    private List<Material> materialList;

    public MaterialAdapter(List<Material> materialList) {
        this.materialList = materialList;
    }

    @NonNull
    @Override
    public MaterialViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(parent.getContext()).inflate(R.layout.item_material, parent, false);
        return new MaterialViewHolder(view);
    }

    @Override
    public void onBindViewHolder(@NonNull MaterialViewHolder holder, int position) {
        Material material = materialList.get(position);
        holder.tvTitle.setText(material.getTitle());
        holder.tvSubject.setText(material.getSubject());
        holder.tvDescription.setText(material.getDescription());

        holder.btnOpen.setOnClickListener(v -> {
            Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(material.getFileUrl()));
            v.getContext().startActivity(intent);
        });
    }

    @Override
    public int getItemCount() {
        return materialList != null ? materialList.size() : 0;
    }

    public void updateData(List<Material> newMaterials) {
        this.materialList = newMaterials;
        notifyDataSetChanged();
    }

    static class MaterialViewHolder extends RecyclerView.ViewHolder {
        TextView tvTitle, tvSubject, tvDescription;
        Button btnOpen;

        public MaterialViewHolder(@NonNull View itemView) {
            super(itemView);
            tvTitle = itemView.findViewById(R.id.tv_material_title);
            tvSubject = itemView.findViewById(R.id.tv_material_subject);
            tvDescription = itemView.findViewById(R.id.tv_material_description);
            btnOpen = itemView.findViewById(R.id.btn_open_file);
        }
    }
}
