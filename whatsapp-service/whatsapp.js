const {
  default: makeWASocket,
  DisconnectReason,
  useMultiFileAuthState,
  fetchLatestBaileysVersion,
} = require("@whiskeysockets/baileys");
const QRCode = require("qrcode");
const pino = require("pino");

class WhatsAppClient {
  constructor() {
    this.sock = null;
    this.isConnected = false;
    this.qrCode = null;
    this.qrCodeDataURL = null;
    this.reconnectAttempts = 0;
    this.maxReconnectAttempts = 5;
    this.reconnectDelay = 5000;
    this.connectionStatus = "disconnected"; // disconnected, connecting, connected, qr_required
  }

  async initialize() {
    try {
      this.connectionStatus = "connecting";
      const { state, saveCreds } = await useMultiFileAuthState("./auth_info");
      const { version } = await fetchLatestBaileysVersion();

      this.sock = makeWASocket({
        auth: state,
        printQRInTerminal: false,
        logger: pino({ level: "silent" }),
        browser: ["Agenda System", "Chrome", "1.0.0"],
        version,
        connectTimeoutMs: 60000,
      });

      this.sock.ev.on("connection.update", async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
          this.qrCode = qr;
          this.connectionStatus = "qr_required";

          // Generate QR Code as Data URL for frontend display
          try {
            this.qrCodeDataURL = await QRCode.toDataURL(qr);
            console.log("🔐 QR Code generated - Ready for scan");
          } catch (err) {
            console.error("Error generating QR Code:", err);
          }
        }

        if (connection === "close") {
          const statusCode = lastDisconnect?.error?.output?.statusCode;
          const shouldReconnect = statusCode !== DisconnectReason.loggedOut;

          console.log("❌ Connection closed:", {
            statusCode,
            reason: this.getDisconnectReason(statusCode),
            shouldReconnect,
            attempts: this.reconnectAttempts,
          });

          this.isConnected = false;

          // If logged out, clear auth and wait for new QR
          if (statusCode === DisconnectReason.loggedOut) {
            console.log("🚪 User logged out - clearing session");
            this.connectionStatus = "qr_required";
            this.qrCode = null;
            this.qrCodeDataURL = null;
            this.reconnectAttempts = 0;

            // Clear auth info and reinitialize to get new QR
            const fs = require("fs");
            const path = "./auth_info";
            if (fs.existsSync(path)) {
              fs.rmSync(path, { recursive: true, force: true });
              console.log("🗑️ Auth info cleared");
            }

            // Wait a bit then reinitialize to generate new QR
            setTimeout(() => this.initialize(), 2000);
            return;
          }

          this.connectionStatus = "disconnected";

          if (
            shouldReconnect &&
            this.reconnectAttempts < this.maxReconnectAttempts
          ) {
            this.reconnectAttempts++;
            const delay = this.reconnectDelay * this.reconnectAttempts;

            console.log(
              `🔄 Reconnecting in ${delay / 1000}s (Attempt ${
                this.reconnectAttempts
              }/${this.maxReconnectAttempts})...`
            );

            setTimeout(() => this.initialize(), delay);
          } else if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.log("⚠️ Max reconnection attempts reached. Resetting...");
            this.connectionStatus = "qr_required";
            this.reconnectAttempts = 0;
            setTimeout(() => this.initialize(), 2000);
          }
        } else if (connection === "open") {
          console.log("✅ WhatsApp connected successfully!");
          this.isConnected = true;
          this.connectionStatus = "connected";
          this.qrCode = null;
          this.qrCodeDataURL = null;
          this.reconnectAttempts = 0;
        }
      });

      this.sock.ev.on("creds.update", saveCreds);
    } catch (error) {
      console.error("❌ Initialization error:", error);
      this.connectionStatus = "disconnected";

      // Retry initialization after error
      if (this.reconnectAttempts < this.maxReconnectAttempts) {
        this.reconnectAttempts++;
        setTimeout(() => this.initialize(), this.reconnectDelay);
      } else {
        // Clear and restart
        this.connectionStatus = "qr_required";
        this.reconnectAttempts = 0;
        setTimeout(() => this.initialize(), 2000);
      }
    }
  }

  getDisconnectReason(statusCode) {
    const reasons = {
      [DisconnectReason.badSession]: "Bad Session",
      [DisconnectReason.connectionClosed]: "Connection Closed",
      [DisconnectReason.connectionLost]: "Connection Lost",
      [DisconnectReason.connectionReplaced]: "Connection Replaced",
      [DisconnectReason.loggedOut]: "Logged Out",
      [DisconnectReason.restartRequired]: "Restart Required",
      [DisconnectReason.timedOut]: "Timed Out",
    };
    return reasons[statusCode] || "Unknown";
  }

  async sendMessage(phoneNumber, message) {
    if (!this.isConnected) {
      throw new Error(
        "WhatsApp belum terhubung. Status: " + this.connectionStatus
      );
    }

    try {
      // Format phone number (remove +, spaces, etc)
      const formattedNumber = phoneNumber.replace(/[^0-9]/g, "");
      const jid = formattedNumber.includes("@s.whatsapp.net")
        ? formattedNumber
        : `${formattedNumber}@s.whatsapp.net`;

      await this.sock.sendMessage(jid, { text: message });
      console.log(`✅ Message sent to ${phoneNumber}`);
      return { success: true, message: "Pesan berhasil dikirim" };
    } catch (error) {
      console.error(
        `❌ Failed to send message to ${phoneNumber}:`,
        error.message
      );
      throw error;
    }
  }

  getStatus() {
    return {
      connected: this.isConnected,
      status: this.connectionStatus,
      qrCode: this.qrCode,
      qrCodeDataURL: this.qrCodeDataURL,
      needsQR: this.connectionStatus === "qr_required",
      reconnectAttempts: this.reconnectAttempts,
      maxReconnectAttempts: this.maxReconnectAttempts,
    };
  }

  async logout() {
    if (this.sock) {
      await this.sock.logout();
      this.isConnected = false;
      this.connectionStatus = "disconnected";
      this.qrCode = null;
      this.qrCodeDataURL = null;
      console.log("🚪 Logged out from WhatsApp");
    }
  }
}

module.exports = WhatsAppClient;
