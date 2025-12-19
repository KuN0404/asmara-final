<template>
  <aside class="sidebar" :class="{ 'mobile-hidden': !visible }">
    <div class="sidebar-header">
      <div class="logo">
        <div class="logo-icon">BPS</div>
        <div class="logo-text">
          <h1>BPS Admin</h1>
          <p>Sistem Agenda Kantor</p>
        </div>
      </div>
      <div class="admin-info" v-if="authStore.user">
        <div class="admin-name">{{ authStore.user.name }}</div>
        <div class="admin-role">{{ getRoleName(authStore.user.roles[0]?.name) }}</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section">
        <div class="nav-section-title">Menu Utama</div>
        <router-link to="/" class="nav-item" active-class="active">
          <span class="nav-icon">📊</span>
          <span>Dashboard</span>
        </router-link>
        <router-link to="/announcements" class="nav-item" active-class="active">
          <span class="nav-icon">📋</span>
          <span>Pengumuman</span>
        </router-link>
        <router-link to="/office-agenda" class="nav-item" active-class="active">
          <span class="nav-icon">📅</span>
          <span>Agenda Kantor</span>
        </router-link>
        <router-link to="/my-agenda" class="nav-item" active-class="active">
          <span class="nav-icon">📅</span>
          <span>Agenda Saya</span>
        </router-link>
      </div>

      <div class="nav-section" v-if="canAccessAdmin">
        <div class="nav-section-title">Master Data</div>
        <router-link to="/users" class="nav-item" active-class="active">
          <span class="nav-icon">👥</span>
          <span>Pengguna</span>
        </router-link>
        <router-link to="/rooms" class="nav-item" active-class="active">
          <span class="nav-icon">🏢</span>
          <span>Ruangan</span>
        </router-link>
        <router-link to="/participants" class="nav-item" active-class="active">
          <span class="nav-icon">👤</span>
          <span>Partisipan</span>
        </router-link>
      </div>

      <!-- ✨ TAMBAHAN SECTION WHATSAPP -->
      <div class="nav-section" v-if="canAccessAdmin">
        <div class="nav-section-title">Notifikasi</div>
        <router-link to="/whatsapp" class="nav-item" active-class="active">
          <span class="nav-icon">📱</span>
          <span>WhatsApp</span>
          <span class="status-indicator" :class="whatsappStatusClass"></span>
        </router-link>
      </div>
      <!-- END TAMBAHAN -->

      <div class="nav-section">
        <div class="nav-section-title">Akun</div>
        <router-link to="/profile" class="nav-item" active-class="active">
          <span class="nav-icon">👤</span>
          <span>Profil Saya</span>
        </router-link>
      </div>
    </nav>
  </aside>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import axios from 'axios'

defineProps({
  visible: {
    type: Boolean,
    default: true,
  },
})

const authStore = useAuthStore()
const router = useRouter()

// ✨ TAMBAHAN: WhatsApp Status
const whatsappStatus = ref('disconnected')
let statusCheckInterval = null

const canAccessAdmin = computed(() => {
  return authStore.hasRole('super_admin') || authStore.hasRole('kepala') || authStore.hasRole('ketua_tim') || authStore.hasRole('kasubbag')
})

// ✨ TAMBAHAN: WhatsApp Status Class
const whatsappStatusClass = computed(() => {
  if (whatsappStatus.value === 'connected') return 'status-connected'
  if (whatsappStatus.value === 'qr_required') return 'status-warning'
  return 'status-disconnected'
})

// ✨ TAMBAHAN: Check WhatsApp Status
const checkWhatsAppStatus = async () => {
  if (!canAccessAdmin.value) return

  try {
    const response = await axios.get('http://localhost:3030/status')
    if (response.data.connected) {
      whatsappStatus.value = 'connected'
    } else if (response.data.needsQR) {
      whatsappStatus.value = 'qr_required'
    } else {
      whatsappStatus.value = 'disconnected'
    }
  } catch (error) {
    whatsappStatus.value = 'disconnected'
  }
}

onMounted(() => {
  // Check status immediately
  checkWhatsAppStatus()

  // Check status every 30 seconds
  statusCheckInterval = setInterval(checkWhatsAppStatus, 30000)
})

onUnmounted(() => {
  if (statusCheckInterval) {
    clearInterval(statusCheckInterval)
  }
})
// END TAMBAHAN

const getRoleName = (role) => {
  const roles = {
    super_admin: 'Super Admin',
    kepala: 'Kepala',
    ketua_tim: 'Ketua Tim',
    kasubbag: 'Kasubbag',
    staff: 'Staff',
  }
  return roles[role] || role
}
</script>

<style scoped>
/* Existing styles... */

/* ✨ TAMBAHAN: Status Indicator Styles */
.status-indicator {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-left: auto;
  animation: pulse 2s infinite;
}

.status-connected {
  background: #4ade80;
  box-shadow: 0 0 8px rgba(74, 222, 128, 0.6);
}

.status-warning {
  background: #fbbf24;
  box-shadow: 0 0 8px rgba(251, 191, 36, 0.6);
}

.status-disconnected {
  background: #94a3b8;
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

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 8px;
  transition: all 0.3s;
  position: relative;
}
/* END TAMBAHAN */
</style>
