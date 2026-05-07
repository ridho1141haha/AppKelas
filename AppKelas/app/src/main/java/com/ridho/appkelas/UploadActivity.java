package com.ridho.appkelas;

import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;
import android.util.Log;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.appcompat.app.AppCompatActivity;

import com.google.gson.JsonObject;

import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;

import okhttp3.MediaType;
import okhttp3.MultipartBody;
import okhttp3.RequestBody;
import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class UploadActivity extends AppCompatActivity {

    private EditText etTitle, etSubject, etDescription;
    private TextView tvSelectedFile;
    private Button btnPick, btnUpload;
    private ProgressBar progressBar;
    private Uri selectedFileUri;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_upload);

        etTitle = findViewById(R.id.et_upload_title);
        etSubject = findViewById(R.id.et_upload_subject);
        etDescription = findViewById(R.id.et_upload_description);
        tvSelectedFile = findViewById(R.id.tv_selected_file);
        btnPick = findViewById(R.id.btn_pick_file);
        btnUpload = findViewById(R.id.btn_start_upload);
        progressBar = findViewById(R.id.pb_upload);

        // File Picker
        ActivityResultLauncher<String> filePicker = registerForActivityResult(
                new ActivityResultContracts.GetContent(),
                uri -> {
                    if (uri != null) {
                        selectedFileUri = uri;
                        tvSelectedFile.setText("File terpilih: " + uri.getLastPathSegment());
                    }
                }
        );

        btnPick.setOnClickListener(v -> filePicker.launch("*/*"));

        btnUpload.setOnClickListener(v -> {
            if (validateInput()) {
                performUpload();
            }
        });
    }

    private boolean validateInput() {
        if (etTitle.getText().toString().isEmpty()) return false;
        if (etSubject.getText().toString().isEmpty()) return false;
        if (selectedFileUri == null) {
            Toast.makeText(this, "Pilih file dulu bos!", Toast.LENGTH_SHORT).show();
            return false;
        }
        return true;
    }

    private void performUpload() {
        setLoading(true);
        try {
            // 1. Copy file URI ke temp file biar bisa di-upload
            File file = getFileFromUri(selectedFileUri);
            
            RequestBody requestFile = RequestBody.create(MediaType.parse(getContentResolver().getType(selectedFileUri)), file);
            MultipartBody.Part body = MultipartBody.Part.createFormData("file", file.getName(), requestFile);

            ApiInterface api = ApiClient.getClient(this).create(ApiInterface.class);
            
            // Step 1: Upload Fisik File
            api.uploadFile(body).enqueue(new Callback<JsonObject>() {
                @Override
                public void onResponse(Call<JsonObject> call, Response<JsonObject> response) {
                    if (response.isSuccessful() && response.body() != null) {
                        String fileUrl = response.body().get("data").getAsJsonObject().get("file_url").getAsString();
                        // Step 2: Simpan metadata ke database
                        saveToDatabase(fileUrl);
                    } else {
                        setLoading(false);
                        Toast.makeText(UploadActivity.this, "Gagal upload file fisik", Toast.LENGTH_SHORT).show();
                    }
                }

                @Override
                public void onFailure(Call<JsonObject> call, Throwable t) {
                    setLoading(false);
                    Log.e("Upload", t.getMessage());
                    Toast.makeText(UploadActivity.this, "Error: " + t.getMessage(), Toast.LENGTH_SHORT).show();
                }
            });

        } catch (Exception e) {
            setLoading(false);
            Toast.makeText(this, "Gagal memproses file", Toast.LENGTH_SHORT).show();
        }
    }

    private void saveToDatabase(String fileUrl) {
        String title = etTitle.getText().toString().trim();
        String subject = etSubject.getText().toString().trim();
        String desc = etDescription.getText().toString().trim();

        ApiInterface api = ApiClient.getClient(this).create(ApiInterface.class);
        api.saveMaterial(title, subject, desc, fileUrl).enqueue(new Callback<JsonObject>() {
            @Override
            public void onResponse(Call<JsonObject> call, Response<JsonObject> response) {
                setLoading(false);
                if (response.isSuccessful()) {
                    Toast.makeText(UploadActivity.this, "Materi Berhasil Diupload!", Toast.LENGTH_LONG).show();
                    finish();
                }
            }

            @Override
            public void onFailure(Call<JsonObject> call, Throwable t) {
                setLoading(false);
                Toast.makeText(UploadActivity.this, "Gagal simpan data", Toast.LENGTH_SHORT).show();
            }
        });
    }

    private void setLoading(boolean loading) {
        progressBar.setVisibility(loading ? View.VISIBLE : View.GONE);
        btnUpload.setEnabled(!loading);
        btnPick.setEnabled(!loading);
    }

    private File getFileFromUri(Uri uri) throws Exception {
        InputStream inputStream = getContentResolver().openInputStream(uri);
        File tempFile = File.createTempFile("upload", ".tmp", getCacheDir());
        tempFile.deleteOnExit();
        FileOutputStream out = new FileOutputStream(tempFile);
        byte[] buffer = new byte[1024];
        int read;
        while ((read = inputStream.read(buffer)) != -1) {
            out.write(buffer, 0, read);
        }
        out.close();
        return tempFile;
    }
}
