const express = require("express");
const cors = require("cors");
const WhatsAppClient = require("./whatsapp");

const app = express();
app.use(cors());
app.use(express.json());

const whatsappClient = new WhatsAppClient();

// Initialize WhatsApp client
whatsappClient.initialize().catch(console.error);

// Health check endpoint
app.get("/health", (req, res) => {
  res.json({
    status: "ok",
    timestamp: new Date().toISOString(),
    whatsapp: whatsappClient.getStatus(),
  });
});

// Status endpoint with detailed info
app.get("/status", (req, res) => {
  const status = whatsappClient.getStatus();
  res.json(status);
});

// QR Code endpoint (returns data URL for frontend)
app.get("/qr-code", (req, res) => {
  const status = whatsappClient.getStatus();

  if (status.qrCodeDataURL) {
    res.json({
      available: true,
      qrCodeDataURL: status.qrCodeDataURL,
      message: "Scan QR code untuk login WhatsApp",
    });
  } else if (status.connected) {
    res.json({
      available: false,
      connected: true,
      message: "WhatsApp sudah terhubung",
    });
  } else {
    res.json({
      available: false,
      connected: false,
      status: status.status,
      message: "Sedang menginisialisasi...",
    });
  }
});

// Send message endpoint
app.post("/send-message", async (req, res) => {
  const { phone, message } = req.body;

  if (!phone || !message) {
    return res.status(400).json({
      success: false,
      error: "Parameter phone dan message wajib diisi",
    });
  }

  try {
    const result = await whatsappClient.sendMessage(phone, message);
    res.json(result);
  } catch (error) {
    res.status(500).json({
      success: false,
      error: error.message,
    });
  }
});

// Logout endpoint
app.post("/logout", async (req, res) => {
  try {
    await whatsappClient.logout();
    res.json({
      success: true,
      message: "Logged out successfully",
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      error: error.message,
    });
  }
});

// Reconnect endpoint (force reconnection)
app.post("/reconnect", async (req, res) => {
  try {
    whatsappClient.reconnectAttempts = 0;
    await whatsappClient.initialize();
    res.json({
      success: true,
      message: "Reconnection initiated",
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      error: error.message,
    });
  }
});

const PORT = process.env.PORT || 3030;
app.listen(PORT, () => {
  console.log(`🚀 WhatsApp Service running on http://localhost:${PORT}`);
  console.log(`📱 Waiting for QR Code to scan with your WhatsApp`);
});
