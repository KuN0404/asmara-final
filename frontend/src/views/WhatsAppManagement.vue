<template>
  <AdminLayout>
    <div class="whatsapp-management">
      <div class="page-header">
        <h1 class="page-title">📱 WhatsApp Management</h1>
        <p class="page-description">Kelola koneksi WhatsApp untuk notifikasi sistem</p>
      </div>

      <div class="card">
        <div class="card-header-custom">
          <h3 class="card-title-custom">Status Koneksi</h3>
          <div class="status-badge" :class="statusClass">
            <span class="status-dot"></span>
            {{ statusText }}
          </div>
        </div>

        <div class="card-body">
          <!-- Connected State -->
          <div v-if="status.connected" class="state-container connected-state">
            <div class="icon-wrapper">
              <svg class="checkmark" viewBox="0 0 52 52">
                <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none" />
                <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
              </svg>
            </div>
            <h4>WhatsApp Terhubung</h4>
            <p>Sistem notifikasi WhatsApp aktif dan siap mengirim pesan.</p>

            <div class="button-group">
              <button @click="testNotification" class="btn btn-primary" :disabled="loading">
                <span v-if="!loading">🧪 Test Notifikasi</span>
                <span v-else>⏳ Mengirim...</span>
              </button>
              <button @click="logout" class="btn btn-danger" :disabled="loading">
                🚪 Logout WhatsApp
              </button>
            </div>
          </div>

          <!-- QR Code State -->
          <div v-else-if="status.needsQR && qrCodeData" class="state-container qr-state">
            <div class="qr-wrapper">
              <img :src="qrCodeData" alt="QR Code WhatsApp" class="qr-image" />
            </div>
            <h4>Scan QR Code</h4>
            <p>Buka WhatsApp di HP Anda dan scan QR code di atas:</p>
            <ol class="instructions">
              <li>Buka WhatsApp di HP</li>
              <li>Tap menu (⋮) atau Settings</li>
              <li>Pilih "Linked Devices"</li>
              <li>Tap "Link a Device"</li>
              <li>Scan QR code di atas</li>
            </ol>
            <button @click="refreshQR" class="btn btn-secondary" :disabled="loading">
              🔄 Refresh QR Code
            </button>
          </div>

          <!-- Loading State -->
          <div v-else class="state-container loading-state">
            <div class="spinner"></div>
            <h4>Menginisialisasi...</h4>
            <p>Mohon tunggu, sedang menyiapkan koneksi WhatsApp.</p>
            <div class="progress-bar">
              <div class="progress-fill" :style="{ width: progress + '%' }"></div>
            </div>
            <p class="small-text">
              Reconnect attempts: {{ status.reconnectAttempts }}/{{ status.maxReconnectAttempts }}
            </p>
          </div>

          <!-- Connection Stats -->
          <div v-if="logs.length > 0" class="logs-section">
            <h5>📊 Riwayat Notifikasi (5 Terakhir)</h5>
            <div class="log-list">
              <div v-for="log in logs.slice(0, 5)" :key="log.id" class="log-item">
                <div class="log-header">
                  <span class="log-status" :class="log.status">{{ log.status }}</span>
                  <span class="log-time">{{ formatDate(log.created_at) }}</span>
                </div>
                <div class="log-body">
                  <p>
                    <strong>{{ getTypeLabel(log.type) }}</strong> -
                    {{ getTriggerLabel(log.trigger) }}
                  </p>
                  <p class="log-phone">{{ log.phone_number }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted, computed, onUnmounted } from 'vue'
import AdminLayout from '@/layouts/AdminLayout.vue'
import axios from 'axios'

const WHATSAPP_SERVICE_URL = 'http://localhost:3030'
const API_URL = 'http://localhost:8000/api'

const status = ref({
  connected: false,
  status: 'disconnected',
  needsQR: false,
  reconnectAttempts: 0,
  maxReconnectAttempts: 5,
})

const qrCodeData = ref(null)
const loading = ref(false)
const logs = ref([])
const progress = ref(0)
let statusInterval = null
let progressInterval = null

const statusClass = computed(() => {
  if (status.value.connected) return 'status-connected'
  if (status.value.needsQR) return 'status-qr'
  return 'status-loading'
})

const statusText = computed(() => {
  if (status.value.connected) return 'Terhubung'
  if (status.value.needsQR) return 'Perlu Scan QR'
  return 'Menghubungkan...'
})

const checkStatus = async () => {
  try {
    const response = await axios.get(`${WHATSAPP_SERVICE_URL}/status`)
    status.value = response.data

    if (response.data.needsQR) {
      await fetchQRCode()
    } else {
      qrCodeData.value = null
    }
  } catch (error) {
    console.error('Failed to check status:', error)
    status.value.status = 'disconnected'
  }
}

const fetchQRCode = async () => {
  try {
    const response = await axios.get(`${WHATSAPP_SERVICE_URL}/qr-code`)
    if (response.data.qrCodeDataURL) {
      qrCodeData.value = response.data.qrCodeDataURL
    }
  } catch (error) {
    console.error('Failed to fetch QR code:', error)
  }
}

const refreshQR = async () => {
  loading.value = true
  try {
    await axios.post(`${WHATSAPP_SERVICE_URL}/reconnect`)
    setTimeout(async () => {
      await checkStatus()
      loading.value = false
    }, 2000)
  } catch (error) {
    console.error('Failed to refresh QR:', error)
    alert('Gagal refresh QR code: ' + error.message)
    loading.value = false
  }
}

const logout = async () => {
  if (!confirm('Yakin ingin logout dari WhatsApp? Anda harus scan QR code ulang.')) return

  loading.value = true
  try {
    await axios.post(`${WHATSAPP_SERVICE_URL}/logout`)
    qrCodeData.value = null
    setTimeout(async () => {
      await checkStatus()
      loading.value = false
    }, 2000)
  } catch (error) {
    console.error('Failed to logout:', error)
    alert('Gagal logout: ' + error.message)
    loading.value = false
  }
}

const testNotification = async () => {
  loading.value = true
  try {
    const token = localStorage.getItem('token')
    const response = await axios.post(
      `${API_URL}/whatsapp/send-test`,
      {},
      {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      },
    )
    alert(response.data.message || 'Test notification sent!')
    await fetchLogs()
  } catch (error) {
    const errorMsg = error.response?.data?.message || error.message
    alert('Failed to send test notification: ' + errorMsg)
  } finally {
    loading.value = false
  }
}

const fetchLogs = async () => {
  try {
    const token = localStorage.getItem('token')
    const response = await axios.get(`${API_URL}/whatsapp/logs`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
    logs.value = response.data.data || []
  } catch (error) {
    console.error('Failed to fetch logs:', error)
  }
}

const formatDate = (dateString) => {
  const date = new Date(dateString)
  return date.toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const getTypeLabel = (type) => {
  const labels = {
    announcement: 'Pengumuman',
    office_agenda: 'Agenda Kantor',
    my_agenda: 'Agenda Pribadi',
    test: 'Test',
  }
  return labels[type] || type
}

const getTriggerLabel = (trigger) => {
  const labels = {
    created: 'Baru Dibuat',
    h_minus_1: 'Reminder H-1',
    h_day: 'Reminder H-Day',
    manual: 'Manual Test',
  }
  return labels[trigger] || trigger
}

const startProgressSimulation = () => {
  progress.value = 0
  progressInterval = setInterval(() => {
    if (progress.value < 90) {
      progress.value += Math.random() * 10
    }
  }, 500)
}

const stopProgressSimulation = () => {
  if (progressInterval) {
    clearInterval(progressInterval)
    progress.value = 100
  }
}

onMounted(async () => {
  await checkStatus()
  await fetchLogs()

  // Poll status every 3 seconds
  statusInterval = setInterval(async () => {
    await checkStatus()
    if (status.value.connected) {
      stopProgressSimulation()
    } else if (!status.value.needsQR) {
      if (!progressInterval) startProgressSimulation()
    }
  }, 3000)
})

onUnmounted(() => {
  if (statusInterval) clearInterval(statusInterval)
  if (progressInterval) clearInterval(progressInterval)
})
</script>

<style scoped>
.whatsapp-management {
  padding: 24px;
  max-width: 1200px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 32px;
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 8px 0;
}

.page-description {
  color: #64748b;
  font-size: 16px;
  margin: 0;
}

.card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.card-header-custom {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px;
  border-bottom: 1px solid #e2e8f0;
  background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
}

.card-title-custom {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
  color: white;
}

.status-badge {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
  background: rgba(255, 255, 255, 0.2);
  color: white;
}

.status-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  animation: pulse 2s infinite;
}

.status-connected .status-dot {
  background: #4ade80;
}

.status-qr .status-dot {
  background: #fbbf24;
}

.status-loading .status-dot {
  background: #60a5fa;
}

@keyframes pulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

.card-body {
  padding: 32px;
}

.state-container {
  text-align: center;
  padding: 20px;
}

.icon-wrapper {
  display: flex;
  justify-content: center;
  margin-bottom: 20px;
}

.checkmark {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  stroke-width: 2;
  stroke: #4ade80;
  stroke-miterlimit: 10;
  animation:
    fill 0.4s ease-in-out 0.4s forwards,
    scale 0.3s ease-in-out 0.9s both;
}

.checkmark__circle {
  stroke-dasharray: 166;
  stroke-dashoffset: 166;
  stroke-width: 2;
  stroke-miterlimit: 10;
  stroke: #4ade80;
  fill: none;
  animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
}

.checkmark__check {
  transform-origin: 50% 50%;
  stroke-dasharray: 48;
  stroke-dashoffset: 48;
  animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
}

@keyframes stroke {
  100% {
    stroke-dashoffset: 0;
  }
}

@keyframes scale {
  0%,
  100% {
    transform: none;
  }
  50% {
    transform: scale3d(1.1, 1.1, 1);
  }
}

.qr-wrapper {
  display: inline-block;
  padding: 20px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  margin-bottom: 20px;
}

.qr-image {
  width: 280px;
  height: 280px;
  display: block;
}

.instructions {
  text-align: left;
  display: inline-block;
  margin: 20px 0;
  padding-left: 20px;
}

.instructions li {
  margin: 8px 0;
  color: #64748b;
}

.spinner {
  width: 60px;
  height: 60px;
  border: 4px solid #f3f4f6;
  border-top-color: #25d366;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 20px;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.progress-bar {
  width: 100%;
  max-width: 400px;
  height: 8px;
  background: #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
  margin: 20px auto;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #25d366, #128c7e);
  transition: width 0.3s ease;
}

.button-group {
  display: flex;
  gap: 12px;
  justify-content: center;
  margin-top: 24px;
  flex-wrap: wrap;
}

.btn {
  padding: 12px 24px;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  background: #25d366;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #128c7e;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
}

.btn-secondary {
  background: #64748b;
  color: white;
}

.btn-secondary:hover:not(:disabled) {
  background: #475569;
}

.btn-danger {
  background: #ef4444;
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background: #dc2626;
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.logs-section {
  margin-top: 32px;
  padding-top: 32px;
  border-top: 2px solid #e5e7eb;
}

.logs-section h5 {
  margin-bottom: 16px;
  color: #1f2937;
  font-size: 18px;
}

.log-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.log-item {
  background: #f9fafb;
  border-radius: 8px;
  padding: 12px;
  border-left: 4px solid #25d366;
}

.log-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
  flex-wrap: wrap;
  gap: 8px;
}

.log-status {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.log-status.success {
  background: #d1fae5;
  color: #065f46;
}

.log-status.failed {
  background: #fee2e2;
  color: #991b1b;
}

.log-time {
  color: #6b7280;
  font-size: 13px;
}

.log-body p {
  margin: 4px 0;
  font-size: 14px;
  color: #374151;
}

.log-phone {
  color: #6b7280;
  font-size: 13px;
}

.small-text {
  margin-top: 12px;
  font-size: 13px;
  color: #6b7280;
}

h4 {
  margin: 16px 0 8px;
  color: #1f2937;
  font-size: 20px;
}

p {
  color: #64748b;
  line-height: 1.6;
}

@media (max-width: 768px) {
  .whatsapp-management {
    padding: 16px;
  }

  .page-title {
    font-size: 24px;
  }

  .card-header-custom {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }

  .qr-image {
    width: 240px;
    height: 240px;
  }

  .button-group {
    flex-direction: column;
    width: 100%;
  }

  .btn {
    width: 100%;
  }
}
</style>
