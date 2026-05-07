package com.ridho.appkelas;

import android.os.Bundle;
import android.util.Log;
import android.view.View;
import android.widget.EditText;
import android.widget.HorizontalScrollView;
import android.widget.ImageButton;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;

import androidx.activity.EdgeToEdge;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;

import com.google.gson.JsonObject;
import com.ridho.appkelas.models.ChatMessage;

import java.util.ArrayList;
import java.util.List;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class ChatActivity extends AppCompatActivity {

    private static final String TAG = "ChatActivity";
    private RecyclerView rvChat;
    private ChatAdapter chatAdapter;
    private EditText etMessage;
    private ImageButton btnSend;
    private HorizontalScrollView suggestionContainer;
    private LinearLayout llSuggestions;
    private List<ChatMessage> chatList;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        EdgeToEdge.enable(this);
        setContentView(R.layout.activity_chat);

        ViewCompat.setOnApplyWindowInsetsListener(findViewById(R.id.chat_container), (v, insets) -> {
            Insets systemBars = insets.getInsets(WindowInsetsCompat.Type.systemBars() | WindowInsetsCompat.Type.ime());
            v.setPadding(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom);
            return insets;
        });

        // Inisialisasi View
        rvChat = findViewById(R.id.rv_chat);
        etMessage = findViewById(R.id.et_chat_message);
        btnSend = findViewById(R.id.btn_send_chat);
        suggestionContainer = findViewById(R.id.suggestion_container);
        llSuggestions = findViewById(R.id.ll_suggestions);

        // Setup RecyclerView
        chatList = new ArrayList<>();
        chatAdapter = new ChatAdapter(chatList);
        rvChat.setLayoutManager(new LinearLayoutManager(this));
        rvChat.setAdapter(chatAdapter);

        // Sapaan awal dari Bot
        chatAdapter.addMessage(new ChatMessage("Halo! 👋 Ada yang bisa saya bantu? Pilih saran di bawah atau ketik langsung!", false));

        // Setup Suggestion Chips
        setupSuggestionChips();

        // Tombol Kirim
        btnSend.setOnClickListener(v -> {
            String message = etMessage.getText().toString().trim();
            if (!message.isEmpty()) {
                sendUserMessage(message);
            }
        });
    }

    /**
     * Setup klik listener untuk setiap chip saran.
     * Setiap chip punya 'tag' yang berisi pesan lengkap untuk dikirim ke AI.
     */
    private void setupSuggestionChips() {
        for (int i = 0; i < llSuggestions.getChildCount(); i++) {
            View child = llSuggestions.getChildAt(i);
            if (child instanceof TextView) {
                child.setOnClickListener(v -> {
                    String message = (String) v.getTag();
                    if (message != null && !message.isEmpty()) {
                        sendUserMessage(message);
                    }
                });
            }
        }
    }

    /**
     * Kirim pesan user: tampilkan di chat, sembunyikan saran, kirim ke API.
     */
    private void sendUserMessage(String message) {
        // 1. Tampilkan pesan user ke list
        chatAdapter.addMessage(new ChatMessage(message, true));
        rvChat.scrollToPosition(chatList.size() - 1);
        etMessage.setText("");

        // 2. Sembunyikan suggestion chips setelah user mulai chat
        hideSuggestions();

        // 3. Kirim ke API Laravel
        sendChatMessage(message);
    }

    /**
     * Sembunyikan suggestion chips dengan animasi fade-out.
     */
    private void hideSuggestions() {
        if (suggestionContainer.getVisibility() == View.VISIBLE) {
            suggestionContainer.animate()
                    .alpha(0f)
                    .setDuration(200)
                    .withEndAction(() -> suggestionContainer.setVisibility(View.GONE))
                    .start();
        }
    }

    private void sendChatMessage(String message) {
        ApiInterface apiInterface = ApiClient.getClient(this).create(ApiInterface.class);
        apiInterface.sendMessage(message).enqueue(new Callback<JsonObject>() {
            @Override
            public void onResponse(Call<JsonObject> call, Response<JsonObject> response) {
                if (response.isSuccessful() && response.body() != null) {
                    try {
                        String botReply = response.body().get("reply").getAsString();
                        chatAdapter.addMessage(new ChatMessage(botReply, false));
                        rvChat.scrollToPosition(chatList.size() - 1);
                    } catch (Exception e) {
                        Log.e(TAG, "Gagal parsing reply: " + e.getMessage());
                        chatAdapter.addMessage(new ChatMessage("Maaf, terjadi kesalahan saat memproses jawaban.", false));
                    }
                } else {
                    Log.e(TAG, "API Error: " + response.code());
                    String errorMsg = "Gagal (Error " + response.code() + ")";
                    try {
                        if (response.errorBody() != null) {
                            errorMsg += ": " + response.errorBody().string();
                        }
                    } catch (Exception e) {
                        Log.e(TAG, "Error reading error body: " + e.getMessage());
                    }
                    chatAdapter.addMessage(new ChatMessage(errorMsg, false));
                    rvChat.scrollToPosition(chatList.size() - 1);
                }
            }

            @Override
            public void onFailure(Call<JsonObject> call, Throwable t) {
                Log.e(TAG, "Network Failure: " + t.getMessage());
                chatAdapter.addMessage(new ChatMessage("Koneksi bermasalah: " + t.getMessage(), false));
            }
        });
    }
}
