package com.ridho.appkelas;

import android.os.Bundle;
import android.util.Log;
import android.widget.Button;
import android.widget.EditText;
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
    private Button btnSend;
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

        // Setup RecyclerView
        chatList = new ArrayList<>();
        chatAdapter = new ChatAdapter(chatList);
        rvChat.setLayoutManager(new LinearLayoutManager(this));
        rvChat.setAdapter(chatAdapter);

        // Sapaan awal dari Bot
        chatAdapter.addMessage(new ChatMessage("Halo! Ada yang bisa saya bantu terkait tugas Anda?", false));
        
        btnSend.setOnClickListener(v -> {
            String message = etMessage.getText().toString().trim();
            if (!message.isEmpty()) {
                // 1. Tampilkan pesan user ke list
                chatAdapter.addMessage(new ChatMessage(message, true));
                rvChat.scrollToPosition(chatList.size() - 1);
                etMessage.setText("");

                // 2. Kirim ke API Laravel
                sendChatMessage(message);
            }
        });
    }

    private void sendChatMessage(String message) {
        ApiInterface apiInterface = ApiClient.getClient().create(ApiInterface.class);
        apiInterface.sendMessage(message).enqueue(new Callback<JsonObject>() {
            @Override
            public void onResponse(Call<JsonObject> call, Response<JsonObject> response) {
                if (response.isSuccessful() && response.body() != null) {
                    try {
                        // Sesuaikan dengan struktur JSON dari Laravel AgentController
                        String botReply = response.body().get("reply").getAsString();
                        chatAdapter.addMessage(new ChatMessage(botReply, false));
                        rvChat.scrollToPosition(chatList.size() - 1);
                    } catch (Exception e) {
                        Log.e(TAG, "Gagal parsing reply: " + e.getMessage());
                        chatAdapter.addMessage(new ChatMessage("Maaf, terjadi kesalahan saat memproses jawaban.", false));
                    }
                } else {
                    Log.e(TAG, "API Error: " + response.code());
                    chatAdapter.addMessage(new ChatMessage("Gagal terhubung ke server (Error " + response.code() + ").", false));
                }
            }

            @Override
            public void onFailure(Call<JsonObject> call, Throwable t) {
                Log.e(TAG, "Network Failure: " + t.getMessage());
                chatAdapter.addMessage(new ChatMessage("Koneksi bermasalah. Pastikan server Laravel aktif.", false));
            }
        });
    }
}
