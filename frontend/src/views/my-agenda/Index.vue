<template>
  <AdminLayout>
    <div class="page-header">
      <h2 class="page-title">Agenda Saya</h2>
      <button @click="openCreateModal" class="btn btn-primary">➕ Tambah Agenda</button>
    </div>

    <div class="calendar-container">
      <div class="calendar-header">
        <div class="calendar-nav">
          <button class="nav-btn" @click="previousMonth">‹</button>
          <button class="calendar-title-btn" @click="showMonthYearPicker = true">
            {{ monthNames[currentMonth] }} {{ currentYear }}
            <span class="picker-icon">📅</span>
          </button>
          <button class="nav-btn" @click="nextMonth">›</button>
        </div>
        <div class="calendar-actions">
          <button class="nav-btn search-btn" @click="showMonthYearPicker = true" title="Cari Bulan & Tahun">
            🔍
          </button>
          <button class="nav-btn" @click="goToToday">Hari Ini</button>
        </div>
      </div>

      <div v-if="loading" class="loading-container">
        <div class="spinner"></div>
        <p>Memuat agenda...</p>
      </div>

      <div v-else class="calendar-grid">
        <div class="weekdays">
          <div class="weekday" v-for="day in weekdays" :key="day">{{ day }}</div>
        </div>

        <div class="days-grid">
          <div
            v-for="day in calendarDays"
            :key="day.date"
            class="day-cell"
            :class="{
              'other-month': !day.isCurrentMonth,
              today: day.isToday,
              'has-events': day.agendas.length > 0,
            }"
            @click="selectDate(day)"
          >
            <div class="day-number">{{ day.day }}</div>
            <div class="day-events">
              <!-- <div
                v-for="agenda in day.agendas.slice(0, 3)"
                :key="agenda.id"
                class="event-item"
                :class="`event-${agenda.status}`"
                @click.stop="viewAgenda(agenda)"
              >
                <span class="event-time">{{ formatTime(agenda.start_at) }}</span>
                <span class="event-title">{{ agenda.title }}</span>
              </div> -->

              <div
                v-for="agenda in day.agendas.slice(0, 3)"
                :key="agenda.id"
                class="event-item"
                :class="[
                  agenda.created_by !== authStore.user?.id
                    ? 'event-other-user'
                    : `event-${agenda.status}`,
                ]"
                @click.stop="viewAgenda(agenda)"
                :title="`${formatTime(agenda.start_at)} - ${agenda.title}`"
              >
                <span class="event-time">{{ formatTime(agenda.start_at) }}</span>
                <span class="event-title">{{ truncateTitle(agenda.title, 15) }}</span>
              </div>

              <div v-if="day.agendas.length > 3" class="event-more">
                +{{ day.agendas.length - 3 }} lainnya
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Agenda List Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showAgendaListModal" class="modal-overlay" @click="closeAgendaListModal">
          <div class="modal-container modal-agenda-list" @click.stop>
            <div class="modal-header">
              <h3>Agenda - {{ formatDateDisplay(selectedDate) }}</h3>
              <button class="modal-close" @click="closeAgendaListModal">✕</button>
            </div>
            <div class="modal-body">
              <div class="agenda-list">
                <div
                  v-for="agenda in selectedDateAgendas"
                  :key="agenda.id"
                  class="agenda-list-item"
                  :class="`agenda-${agenda.status}`"
                  @click="viewAgendaFromList(agenda)"
                >
                  <div class="agenda-list-header">
                    <span class="agenda-list-time">{{ formatTime(agenda.start_at) }} - {{ formatTime(agenda.until_at) }}</span>
                    <span :class="getStatusClass(agenda.status)">
                      {{ getStatusText(agenda.status) }}
                    </span>
                  </div>
                  <h4 class="agenda-list-title">{{ agenda.title }}</h4>
                  <!-- Show creator name if available -->
                  <p v-if="agenda.creator" class="agenda-list-creator">
                    👤 {{ agenda.creator.name }}
                    <span v-if="agenda.created_by === authStore.user?.id" class="own-badge">(Milik Saya)</span>
                  </p>
                </div>
              </div>
              <div class="form-actions">
                <button @click="addAgendaToSelectedDate" class="btn btn-primary">
                  ➕ Tambah Agenda di Tanggal Ini
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showFormModal" class="modal-overlay" @click="closeFormModal">
          <div class="modal-container" @click.stop>
            <div class="modal-header">
              <h3>{{ modalTitle }}</h3>
              <button class="modal-close" @click="closeFormModal">✕</button>
            </div>
            <div class="modal-body">
              <form @submit.prevent="handleSubmit">
                <div class="form-group">
                  <label class="form-label">Judul Agenda *</label>
                  <input
                    type="text"
                    class="form-input"
                    v-model="form.title"
                    placeholder="Masukkan judul agenda"
                    required
                  />
                </div>

                <div class="form-row">
                  <div class="form-group">
                    <label class="form-label">Tanggal & Waktu Mulai *</label>
                    <input
                      type="datetime-local"
                      class="form-input"
                      v-model="form.start_at"
                      :min="getMinDate()"
                      required
                    />
                  </div>
                  <div class="form-group">
                    <label class="form-label">Tanggal & Waktu Selesai *</label>
                    <input
                      type="datetime-local"
                      class="form-input"
                      v-model="form.until_at"
                      :min="form.start_at || getMinDate()"
                      required
                    />
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">Deskripsi</label>
                  <textarea
                    class="form-textarea"
                    v-model="form.description"
                    placeholder="Masukkan deskripsi agenda"
                    rows="3"
                  ></textarea>
                </div>

                <!-- User Selector (Admin Only) -->
                <div class="form-group" v-if="isAdminRole">
                  <label class="form-label">Buat Agenda Untuk</label>
                  <select v-model="form.user_id" class="form-input">
                    <option :value="null">👤 Saya Sendiri</option>
                    <option v-for="user in otherUsers" :key="user.id" :value="user.id">
                      {{ user.name }} ({{ user.email }})
                    </option>
                  </select>
                  <span class="form-hint">Sebagai admin, Anda dapat membuat agenda untuk user lain</span>
                </div>

                <div class="form-group">
                  <label class="checkbox-label">
                    <input type="checkbox" v-model="form.is_show_to_other" class="checkbox" />
                    <span>Tampilkan ke pengguna lain</span>
                  </label>
                </div>

                <div class="form-actions">
                  <button type="button" class="btn btn-secondary" @click="closeFormModal">
                    Batal
                  </button>
                  <button type="submit" class="btn btn-primary" :disabled="submitting">
                    {{ submitting ? 'Menyimpan...' : '💾 Simpan' }}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Detail Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showDetailModal" class="modal-overlay" @click="closeDetailModal">
          <div class="modal-container" @click.stop>
            <div class="modal-header">
              <h3>Detail Agenda</h3>
              <button class="modal-close" @click="closeDetailModal">✕</button>
            </div>
            <div class="modal-body">
              <div v-if="selectedAgenda" class="detail-container">
                <!-- Tombol Aksi Status di Atas -->

                <!-- <div class="status-actions" v-if="canChangeStatus(selectedAgenda)">
                  <button
                    @click="changeStatus(selectedAgenda, 'schedule_change')"
                    class="btn-status btn-status-reschedule"
                  >
                    📅 Ganti Jadwal
                  </button>
                  <button
                    @click="changeStatus(selectedAgenda, 'cancelled')"
                    class="btn-status btn-status-cancel"
                  >
                    ❌ Batalkan
                  </button>
                </div> -->

                <div
                  class="status-actions"
                  v-if="
                    selectedAgenda &&
                    canChangeStatus(selectedAgenda) &&
                    selectedAgenda.created_by === authStore.user?.id
                  "
                >
                  <!-- <button
                    @click="changeStatus(selectedAgenda, 'schedule_change')"
                    class="btn-status btn-status-reschedule"
                  >
                    📅 Ganti Jadwal
                  </button> -->
                  <button
                    @click="changeStatus(selectedAgenda, 'cancelled')"
                    class="btn-status btn-status-cancel"
                  >
                    ❌ Batalkan
                  </button>
                </div>

                <div class="detail-item">
                  <label class="detail-label">Judul:</label>
                  <div class="detail-value">{{ selectedAgenda.title }}</div>
                </div>
                <div class="detail-item">
                  <label class="detail-label">Tanggal Mulai:</label>
                  <div class="detail-value">{{ formatDateTime(selectedAgenda.start_at) }}</div>
                </div>
                <div class="detail-item">
                  <label class="detail-label">Tanggal Selesai:</label>
                  <div class="detail-value">{{ formatDateTime(selectedAgenda.until_at) }}</div>
                </div>
                <div class="detail-item">
                  <label class="detail-label">Status:</label>
                  <div class="detail-value">
                    <span :class="getStatusClass(selectedAgenda.status)">
                      {{ getStatusText(selectedAgenda.status) }}
                    </span>
                  </div>
                </div>
                <div class="detail-item" v-if="selectedAgenda.description">
                  <label class="detail-label">Deskripsi:</label>
                  <div class="detail-value">{{ selectedAgenda.description }}</div>
                </div>
                <div class="detail-item">
                  <label class="detail-label">Tampilkan ke Pengguna Lain:</label>
                  <div class="detail-value">
                    {{ selectedAgenda.is_show_to_other ? 'Ya' : 'Tidak' }}
                  </div>
                </div>
                <div class="detail-item" v-if="selectedAgenda.creator">
                  <label class="detail-label">Dibuat Oleh:</label>
                  <div class="detail-value creator-info">
                    <span class="creator-name">{{ selectedAgenda.creator.name }}</span>
                    <span class="creator-email">{{ selectedAgenda.creator.email }}</span>
                  </div>
                </div>
              </div>

              <!-- <div class="form-actions" v-if="selectedAgenda && canEditOrDelete(selectedAgenda)"> -->
              <!-- <button @click="deleteAgenda(selectedAgenda)" class="btn btn-delete">
                  🗑️ Hapus
                </button> -->
              <!-- <button @click="editAgenda(selectedAgenda)" class="btn btn-primary">✏️ Ubah</button> -->
              <!-- </div> -->

              <div
                class="status-actions"
                v-if="
                  selectedAgenda &&
                  canChangeStatus(selectedAgenda) &&
                  selectedAgenda.created_by === authStore.user?.id
                "
              >
                <button
                  @click="changeStatus(selectedAgenda, 'schedule_change')"
                  class="btn-status btn-status-reschedule"
                >
                  📅 Ganti Jadwal
                </button>
                <button
                  @click="changeStatus(selectedAgenda, 'cancelled')"
                  class="btn-status btn-status-cancel"
                >
                  ❌ Batalkan
                </button>
              </div>

              <!-- Back Button -->
              <div class="detail-actions">
                <button @click="closeDetailModal" class="btn btn-secondary">
                  ← Kembali ke Daftar
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ========== MONTH/YEAR PICKER MODAL ========== -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="showMonthYearPicker" class="picker-overlay" @click.self="showMonthYearPicker = false">
          <div class="picker-modal">
            <div class="picker-header">
              <h3>📅 Pilih Bulan & Tahun</h3>
              <button @click="showMonthYearPicker = false" class="close-picker-btn">✕</button>
            </div>
            <div class="picker-content">
              <!-- Year Selector -->
              <div class="year-selector">
                <button @click="pickerYear--" class="year-nav-btn">‹</button>
                <select v-model="pickerYear" class="year-select">
                  <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                </select>
                <button @click="pickerYear++" class="year-nav-btn">›</button>
              </div>
              
              <!-- Month Grid -->
              <div class="month-grid">
                <button
                  v-for="(month, index) in monthNames"
                  :key="index"
                  class="month-btn"
                  :class="{ 
                    'active': pickerMonth === index && pickerYear === currentYear,
                    'selected': pickerMonth === index
                  }"
                  @click="pickerMonth = index"
                >
                  {{ month.substring(0, 3) }}
                </button>
              </div>
            </div>
            <div class="picker-actions">
              <button @click="showMonthYearPicker = false" class="btn-picker-cancel">Batal</button>
              <button @click="goToSelectedMonthYear" class="btn-picker-go">🚀 Pergi ke Bulan</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notification'
import myAgendaService from '@/services/myAgendaService'

const authStore = useAuthStore()
const notificationStore = useNotificationStore()

const loading = ref(true)
const submitting = ref(false)
const agendas = ref([])
const publicAgendas = ref([])
const users = ref([]) // For admin user selector

// Check if current user is admin
const isAdminRole = computed(() => {
  return authStore.hasRole('super_admin') || authStore.hasRole('kepala') || authStore.hasRole('ketua_tim')
})

// Filter out current user from list
const otherUsers = computed(() => {
  return users.value.filter(u => u.id !== authStore.user?.id)
})

const allAgendas = computed(() => {
  // For admin roles, index() already returns ALL agendas, so no need to merge
  if (isAdminRole.value) {
    return agendas.value
  }
  // For regular users, merge own agendas + public agendas from others
  return [...agendas.value, ...publicAgendas.value]
})

const truncateTitle = (title, maxLength = 15) => {
  if (!title) return ''
  if (title.length <= maxLength) return title
  return title.substring(0, maxLength) + '...'
}

const currentYear = ref(new Date().getFullYear())
const currentMonth = ref(new Date().getMonth())

// Month/Year Picker state
const showMonthYearPicker = ref(false)
const pickerYear = ref(new Date().getFullYear())
const pickerMonth = ref(new Date().getMonth())

// Generate year options (10 years back and 5 years forward)
const yearOptions = computed(() => {
  const currentYr = new Date().getFullYear()
  const years = []
  for (let y = currentYr - 10; y <= currentYr + 5; y++) {
    years.push(y)
  }
  return years
})

// Go to selected month/year
const goToSelectedMonthYear = async () => {
  currentYear.value = pickerYear.value
  currentMonth.value = pickerMonth.value
  showMonthYearPicker.value = false
  await Promise.all([loadAgendas(), loadPublicAgendas()])
}

const monthNames = [
  'Januari',
  'Februari',
  'Maret',
  'April',
  'Mei',
  'Juni',
  'Juli',
  'Agustus',
  'September',
  'Oktober',
  'November',
  'Desember',
]
const weekdays = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']

const showFormModal = ref(false)
const showDetailModal = ref(false)
const showAgendaListModal = ref(false)
const modalTitle = ref('Tambah Agenda')
const selectedAgenda = ref(null)
const selectedDate = ref('')
const selectedDateAgendas = ref([])
const editMode = ref(false)

const form = ref({
  title: '',
  start_at: '',
  until_at: '',
  status: 'comming_soon', // Default, akan di-set otomatis
  description: '',
  is_show_to_other: false,
  user_id: null, // For admin to create agenda for other user
})

const canChangeStatus = (agenda) => {
  // Hanya bisa ganti jadwal/batalkan jika status comming_soon
  return agenda.status === 'comming_soon'
}

// Check if user can edit or delete agenda
const canEditOrDelete = (agenda) => {
  if (!agenda) return false

  const isOwner = agenda.created_by === authStore.user?.id

  // Hanya bisa edit/delete jika status comming_soon
  const canModify = agenda.status === 'comming_soon'

  return isOwner && canModify
}
// Get minimum date (today)
const getMinDate = () => {
  const today = new Date()
  const year = today.getFullYear()
  const month = String(today.getMonth() + 1).padStart(2, '0')
  const day = String(today.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}T00:00`
}

// Change status manually
const changeStatus = async (agenda, newStatus) => {
  if (newStatus === 'cancelled') {
    if (!confirm('Apakah Anda yakin ingin membatalkan agenda ini?')) return

    try {
      // Soft delete via destroy endpoint
      await myAgendaService.delete(agenda.id)
      notificationStore.success('Agenda berhasil dibatalkan')
      showDetailModal.value = false
      await loadAgendas()
    } catch (error) {
      notificationStore.error(error.response?.data?.message || 'Terjadi kesalahan')
    }
  } else if (newStatus === 'schedule_change') {
    // Arahkan ke form edit
    showDetailModal.value = false
    editAgenda(agenda)
  }
}
// Format date untuk timezone Jakarta
const formatDateLocal = (date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

// Format datetime untuk display
const formatDateTime = (datetime) => {
  if (!datetime) return ''
  const d = new Date(datetime)
  const date = formatDateLocal(d)
  const time = `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
  return `${date} ${time}`
}

const formatTime = (datetime) => {
  if (!datetime) return ''
  const d = new Date(datetime)
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}

const getStatusText = (status) => {
  const statusMap = {
    comming_soon: 'Akan Datang',
    in_progress: 'Sedang Berlangsung',
    schedule_change: 'Perubahan Jadwal',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
  }
  return statusMap[status] || status
}

const getStatusClass = (status) => {
  return `status-badge status-${status}`
}

const calendarDays = computed(() => {
  const firstDay = new Date(currentYear.value, currentMonth.value, 1)
  const lastDay = new Date(currentYear.value, currentMonth.value + 1, 0)
  const prevLastDay = new Date(currentYear.value, currentMonth.value, 0)

  const firstDayOfWeek = firstDay.getDay()
  const lastDateOfMonth = lastDay.getDate()
  const prevLastDate = prevLastDay.getDate()

  const days = []

  // Previous month days
  for (let i = firstDayOfWeek - 1; i >= 0; i--) {
    const date = new Date(currentYear.value, currentMonth.value - 1, prevLastDate - i)
    days.push(createDayObject(date, false))
  }

  // Current month days
  for (let i = 1; i <= lastDateOfMonth; i++) {
    const date = new Date(currentYear.value, currentMonth.value, i)
    days.push(createDayObject(date, true))
  }

  // Next month days
  const remainingDays = 42 - days.length
  for (let i = 1; i <= remainingDays; i++) {
    const date = new Date(currentYear.value, currentMonth.value + 1, i)
    days.push(createDayObject(date, false))
  }

  return days
})

const createDayObject = (date, isCurrentMonth) => {
  const today = new Date()
  const dateStr = formatDateLocal(date)

  return {
    date: dateStr,
    day: date.getDate(),
    isCurrentMonth,
    isToday: dateStr === formatDateLocal(today),
    agendas: allAgendas.value.filter((a) => {
      if (!a.start_at) return false
      const agendaDate = new Date(a.start_at)
      return formatDateLocal(agendaDate) === dateStr
    }),
  }
}

const previousMonth = async () => {
  if (currentMonth.value === 0) {
    currentMonth.value = 11
    currentYear.value--
  } else {
    currentMonth.value--
  }
  await Promise.all([loadAgendas(), loadPublicAgendas()]) // Fetch ulang data
}

const nextMonth = async () => {
  if (currentMonth.value === 11) {
    currentMonth.value = 0
    currentYear.value++
  } else {
    currentMonth.value++
  }
  await Promise.all([loadAgendas(), loadPublicAgendas()]) // Fetch ulang data
}

const goToToday = async () => {
  const today = new Date()
  currentYear.value = today.getFullYear()
  currentMonth.value = today.getMonth()
  await Promise.all([loadAgendas(), loadPublicAgendas()]) // Fetch ulang data
}

const selectDate = (day) => {
  if (day.agendas.length === 0) {
    // Jika tidak ada agenda, langsung buka form create
    openCreateModalWithDate(day.date)
  } else {
    // Jika ada agenda, tampilkan daftar agenda terlebih dahulu
    selectedDate.value = day.date
    selectedDateAgendas.value = day.agendas
    showAgendaListModal.value = true
  }
}

const openCreateModal = () => {
  editMode.value = false
  modalTitle.value = 'Tambah Agenda'
  resetForm()
  showFormModal.value = true
}

const openCreateModalWithDate = (date) => {
  editMode.value = false
  modalTitle.value = 'Tambah Agenda'
  resetForm()
  form.value.start_at = `${date}T09:00`
  form.value.until_at = `${date}T10:00`
  showFormModal.value = true
}

const viewAgenda = (agenda) => {
  selectedAgenda.value = agenda
  showDetailModal.value = true
}

const editAgenda = (agenda) => {
  showDetailModal.value = false
  editMode.value = true
  modalTitle.value = 'Ubah Agenda'
  selectedAgenda.value = agenda

  const startDate = new Date(agenda.start_at)
  const untilDate = new Date(agenda.until_at)

  form.value = {
    title: agenda.title,
    start_at: startDate.toISOString().substring(0, 16),
    until_at: untilDate.toISOString().substring(0, 16),
    description: agenda.description || '',
    is_show_to_other: agenda.is_show_to_other || false,
  }

  showFormModal.value = true
}

const closeFormModal = () => {
  showFormModal.value = false
  resetForm()
}

const closeDetailModal = () => {
  showDetailModal.value = false
  selectedAgenda.value = null
}

const closeAgendaListModal = () => {
  showAgendaListModal.value = false
  selectedDate.value = ''
  selectedDateAgendas.value = []
}

const viewAgendaFromList = (agenda) => {
  showAgendaListModal.value = false
  viewAgenda(agenda)
}

const addAgendaToSelectedDate = () => {
  showAgendaListModal.value = false
  openCreateModalWithDate(selectedDate.value)
}

const formatDateDisplay = (dateStr) => {
  if (!dateStr) return ''
  const [year, month, day] = dateStr.split('-')
  const monthName = monthNames[parseInt(month) - 1]
  return `${parseInt(day)} ${monthName} ${year}`
}

const resetForm = () => {
  form.value = {
    title: '',
    start_at: '',
    until_at: '',
    description: '',
    is_show_to_other: false,
    user_id: null,
  }
  selectedAgenda.value = null
}

const handleSubmit = async () => {
  const now = new Date()
  const startDate = new Date(form.value.start_at)

  if (startDate < now && !editMode.value) {
    notificationStore.error('Tanggal dan waktu tidak valid')
    return
  }

  submitting.value = true
  try {
    // Auto-set status berdasarkan tanggal

    if (editMode.value) {
      await myAgendaService.update(selectedAgenda.value.id, form.value)
      notificationStore.success('Agenda berhasil diperbarui')
    } else {
      await myAgendaService.create(form.value)
      notificationStore.success('Agenda berhasil ditambahkan')
    }
    await loadAgendas()
    closeFormModal()
  } catch (error) {
    notificationStore.error(error.response?.data?.message || 'Terjadi kesalahan')
  } finally {
    submitting.value = false
  }
}

const loadAgendas = async () => {
  loading.value = true
  try {
    // Range 3 bulan (bulan lalu, bulan ini, bulan depan)
    const startDate = new Date(currentYear.value, currentMonth.value - 1, 1)
    const endDate = new Date(currentYear.value, currentMonth.value + 2, 0)

    const response = await myAgendaService.getAll({
      per_page: 500, // Cukup untuk 3 bulan
    })

    let agendasData = []
    if (response.data && Array.isArray(response.data)) {
      agendasData = response.data
    } else if (response.data && response.data.data && Array.isArray(response.data.data)) {
      agendasData = response.data.data
    } else if (Array.isArray(response)) {
      agendasData = response
    }

    // Filter di frontend untuk 3 bulan
    agendasData = agendasData.filter((agenda) => {
      if (!agenda.start_at) return false
      const agendaDate = new Date(agenda.start_at)
      return agendaDate >= startDate && agendaDate <= endDate
    })

    agendas.value = agendasData
  } catch (error) {
    notificationStore.error('Gagal memuat agenda')
    agendas.value = []
  } finally {
    loading.value = false
  }
}

const loadPublicAgendas = async () => {
  try {
    // Range 3 bulan (bulan lalu, bulan ini, bulan depan)
    const startDate = new Date(currentYear.value, currentMonth.value - 1, 1)
    const endDate = new Date(currentYear.value, currentMonth.value + 2, 0)

    const response = await myAgendaService.getPublicAgendas({
      per_page: 500,
    })

    let publicData = []
    if (response.data && Array.isArray(response.data)) {
      publicData = response.data
    } else if (response.data && response.data.data && Array.isArray(response.data.data)) {
      publicData = response.data.data
    } else if (Array.isArray(response)) {
      publicData = response
    }

    // Filter di frontend untuk 3 bulan
    publicData = publicData.filter((agenda) => {
      if (!agenda.start_at) return false
      const agendaDate = new Date(agenda.start_at)
      return agendaDate >= startDate && agendaDate <= endDate
    })

    publicAgendas.value = publicData
  } catch (error) {
    publicAgendas.value = []
  }
}

// Load all users for admin user selector
const loadUsers = async () => {
  try {
    const response = await fetch('/api/users?per_page=100', {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Accept': 'application/json'
      }
    })
    const data = await response.json()
    users.value = data.data || data || []
  } catch (error) {
    console.error('Failed to load users:', error)
    users.value = []
  }
}

onMounted(async () => {
  // Load users for admin role
  if (isAdminRole.value) {
    await loadUsers()
    // Admin: index() already returns ALL agendas, no need for publicAgendas
    await loadAgendas()
  } else {
    // Regular user: merge own agendas + public agendas
    await Promise.all([loadAgendas(), loadPublicAgendas()])
  }
})
</script>

<style scoped>
.event-other-user {
  background: #ede9fe; /* Ungu muda */
  border-left-color: #7c3aed; /* Ungu tua */
  color: #5b21b6; /* Teks ungu */
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-title {
  font-size: 1.8rem;
  font-weight: 600;
  color: #1e293b;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 8px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary {
  background: #1e40af;
  color: white;
}

.btn-primary:hover {
  background: #1e3a8a;
}

.btn-secondary {
  background: #e5e7eb;
  color: #374151;
}

.btn-secondary:hover {
  background: #d1d5db;
}

/* Form hint for admin user selector */
.form-hint {
  display: block;
  font-size: 0.8rem;
  color: #6b7280;
  margin-top: 6px;
  font-style: italic;
}

.btn-delete {
  background: #ef4444;
  color: white;
}

.btn-delete:hover {
  background: #dc2626;
}

.btn-test {
  background: #10b981;
  color: white;
  padding: 5px 15px;
  font-size: 0.85rem;
}

.debug-info {
  padding: 10px 20px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.debug-info small {
  color: #64748b;
}

.modal-agenda-list {
  max-width: 500px;
}

.agenda-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 20px;
}

.agenda-list-item {
  padding: 16px;
  border-radius: 8px;
  border-left: 4px solid;
  cursor: pointer;
  transition: all 0.2s;
  background: #f9fafb;
}

.agenda-list-item:hover {
  transform: translateX(4px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.agenda-comming_soon {
  border-left-color: #1e40af;
  background: #eff6ff;
}

.agenda-in_progress {
  border-left-color: #059669;
  background: #f0fdf4;
}

.agenda-completed {
  border-left-color: #6b7280;
  background: #f9fafb;
}

.agenda-cancelled {
  border-left-color: #dc2626;
  background: #fef2f2;
  opacity: 0.85;
}

.agenda-cancelled .agenda-list-title {
  text-decoration: line-through;
  color: #991b1b;
}

.agenda-schedule_change {
  border-left-color: #f59e0b;
  background: #fffbeb;
}

/* Creator info in agenda list */
.agenda-list-creator {
  margin: 4px 0 0 0;
  font-size: 0.85rem;
  color: #6b7280;
}

.own-badge {
  background: #dbeafe;
  color: #1e40af;
  font-size: 0.75rem;
  padding: 2px 8px;
  border-radius: 12px;
  margin-left: 6px;
  font-weight: 500;
}

.agenda-list-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.agenda-list-time {
  font-weight: 600;
  color: #374151;
  font-size: 0.9rem;
}

.agenda-list-title {
  margin: 0 0 6px 0;
  font-size: 1.05rem;
  font-weight: 600;
  color: #1e293b;
}

.agenda-list-desc {
  margin: 0;
  font-size: 0.9rem;
  color: #64748b;
  line-height: 1.4;
}

.calendar-container {
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.calendar-header {
  background: #1e40af;
  color: white;
  padding: 20px 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.calendar-nav {
  display: flex;
  align-items: center;
  gap: 20px;
}

.nav-btn {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  transition: background-color 0.2s;
}

.nav-btn:hover {
  background: rgba(255, 255, 255, 0.3);
}

/* Clickable Calendar Title Button */
.calendar-title-btn {
  background: rgba(255, 255, 255, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.3);
  color: white;
  font-size: 1.3rem;
  font-weight: 600;
  padding: 10px 20px;
  border-radius: 10px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s;
}

.calendar-title-btn:hover {
  background: rgba(255, 255, 255, 0.25);
  transform: scale(1.02);
}

.picker-icon {
  font-size: 1rem;
}

.calendar-actions {
  display: flex;
  gap: 10px;
  align-items: center;
}

.search-btn {
  font-size: 1.1rem;
}

/* Month/Year Picker Modal */
.picker-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 10001;
  padding: 20px;
}

.picker-modal {
  background: white;
  border-radius: 16px;
  padding: 24px;
  max-width: 380px;
  width: 100%;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.picker-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e5e7eb;
}

.picker-header h3 {
  margin: 0;
  font-size: 1.2rem;
  color: #1e293b;
}

.close-picker-btn {
  background: #f3f4f6;
  border: none;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 1rem;
  color: #64748b;
  transition: all 0.2s;
}

.close-picker-btn:hover {
  background: #e5e7eb;
  color: #1e293b;
}

.picker-content {
  margin-bottom: 20px;
}

/* Year Selector */
.year-selector {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-bottom: 20px;
}

.year-nav-btn {
  background: #f3f4f6;
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 1.2rem;
  color: #374151;
  transition: all 0.2s;
}

.year-nav-btn:hover {
  background: #e5e7eb;
}

.year-select {
  padding: 10px 20px;
  font-size: 1.2rem;
  font-weight: 600;
  border: 2px solid #1e40af;
  border-radius: 8px;
  background: white;
  color: #1e40af;
  cursor: pointer;
  text-align: center;
  min-width: 120px;
}

.year-select:focus {
  outline: none;
  box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.2);
}

/* Month Grid */
.month-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

.month-btn {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  padding: 12px 8px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 0.9rem;
  font-weight: 500;
  color: #374151;
  transition: all 0.2s;
}

.month-btn:hover {
  background: #eff6ff;
  border-color: #3b82f6;
  color: #1e40af;
}

.month-btn.selected {
  background: #1e40af;
  border-color: #1e40af;
  color: white;
}

.month-btn.active {
  box-shadow: 0 0 0 2px #fbbf24;
}

/* Picker Actions */
.picker-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  padding-top: 16px;
  border-top: 1px solid #e5e7eb;
}

.btn-picker-cancel {
  padding: 10px 20px;
  font-size: 0.9rem;
  font-weight: 500;
  border: 1px solid #e2e8f0;
  background: white;
  color: #64748b;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-picker-cancel:hover {
  background: #f1f5f9;
}

.btn-picker-go {
  padding: 10px 24px;
  font-size: 0.9rem;
  font-weight: 500;
  border: none;
  background: linear-gradient(135deg, #3b82f6, #1e40af);
  color: white;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-picker-go:hover {
  background: linear-gradient(135deg, #2563eb, #1e3a8a);
  transform: translateY(-1px);
}

/* Modal fade transition */
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.3s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

.calendar-title {
  font-size: 1.5rem;
  font-weight: 600;
  margin: 0;
}

.debug-info {
  padding: 10px 20px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.debug-info small {
  color: #64748b;
}

.loading-container {
  padding: 40px;
  text-align: center;
}

.spinner {
  border: 3px solid #f3f4f6;
  border-top: 3px solid #1e40af;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  animation: spin 1s linear infinite;
  margin: 0 auto 10px;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

.calendar-grid {
  padding: 20px;
}

.weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1px;
  margin-bottom: 10px;
}

.weekday {
  padding: 12px;
  text-align: center;
  font-weight: 600;
  color: #64748b;
  background: #f1f5f9;
  font-size: 0.9rem;
}

.days-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1px;
  background: #e2e8f0;
}

.day-cell {
  background: white;
  min-height: 120px;
  padding: 8px;
  cursor: pointer;
  transition: background-color 0.2s;
}

.day-cell:hover {
  background: #f8fafc;
}

.day-cell.other-month {
  background: #f9fafb;
}

.day-cell.other-month .day-number {
  color: #cbd5e1;
}

.day-cell.today {
  background: #dbeafe;
}

.day-cell.today .day-number {
  color: #1e40af;
  font-weight: 700;
}

.day-number {
  font-weight: 600;
  margin-bottom: 5px;
  font-size: 0.9rem;
}

.day-events {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.event-item {
  padding: 3px 6px;
  border-radius: 3px;
  font-size: 0.75rem;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  gap: 4px;
  align-items: center;
  border-left: 3px solid;
  min-width: 0; /* Tambahkan ini */
  overflow: hidden; /* Tambahkan ini */
}

.event-item:hover {
  transform: translateX(2px);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.event-comming_soon {
  background: #dbeafe;
  border-left-color: #1e40af;
  color: #1e40af;
}

.event-in_progress {
  background: #dcfce7;
  border-left-color: #059669;
  color: #059669;
}

.event-completed {
  background: #f3f4f6;
  border-left-color: #6b7280;
  color: #6b7280;
}

.event-cancelled {
  background: #fee2e2;
  border-left-color: #dc2626;
  color: #dc2626;
  text-decoration: line-through;
  opacity: 0.8;
}

.event-schedule_change {
  background: #fef3c7;
  border-left-color: #f59e0b;
  color: #f59e0b;
}

.event-time {
  font-weight: 600;
  white-space: nowrap;
  flex-shrink: 0; /* Tambahkan ini */
}

.event-title {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  flex: 1; /* Tambahkan ini */
  min-width: 0; /* Tambahkan ini */
}

.event-more {
  font-size: 0.7rem;
  color: #64748b;
  padding: 2px 6px;
  text-align: center;
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
  padding: 20px;
}

.modal-container {
  background: white;
  border-radius: 12px;
  max-width: 600px;
  width: 100%;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.modal-header {
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
}

.modal-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  color: #6b7280;
  cursor: pointer;
  padding: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  transition: background-color 0.2s;
}

.modal-close:hover {
  background: #f3f4f6;
}

.modal-body {
  padding: 24px;
  overflow-y: auto;
}

.form-group {
  margin-bottom: 16px;
}

.form-label {
  display: block;
  margin-bottom: 6px;
  font-weight: 500;
  color: #374151;
  font-size: 0.9rem;
}

.form-input,
.form-select,
.form-textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.95rem;
  transition: border-color 0.2s;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: #1e40af;
}

.form-textarea {
  resize: vertical;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
}

.checkbox {
  width: 20px;
  height: 20px;
  cursor: pointer;
}

.form-actions {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-top: 24px;
  padding-top: 16px;
  border-top: 1px solid #e5e7eb;
}

.action-buttons {
  display: flex;
  gap: 12px;
}

.status-actions {
  display: flex;
  gap: 12px;
  padding: 16px;
  background: #f8fafc;
  border-radius: 8px;
  margin-bottom: 16px;
}

.btn-status {
  flex: 1;
  padding: 10px 16px;
  border: none;
  border-radius: 6px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 0.9rem;
}

.btn-status-reschedule {
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fbbf24;
}

.btn-status-reschedule:hover {
  background: #fde68a;
}

.btn-status-cancel {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #f87171;
}

.btn-status-cancel:hover {
  background: #fecaca;
}

.detail-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.detail-label {
  font-weight: 600;
  color: #64748b;
  font-size: 0.85rem;
}

.detail-value {
  color: #1e293b;
  font-size: 0.95rem;
}

.creator-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.creator-name {
  font-weight: 600;
  color: #1e40af;
}

.creator-email {
  font-size: 0.85rem;
  color: #64748b;
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 0.85rem;
  font-weight: 500;
}

.status-comming_soon {
  background: #dbeafe;
  color: #1e40af;
}

.status-in_progress {
  background: #dcfce7;
  color: #059669;
}

.status-completed {
  background: #f3f4f6;
  color: #6b7280;
}

.status-cancelled {
  background: #fee2e2;
  color: #dc2626;
  font-weight: 600;
}

.status-schedule_change {
  background: #fef3c7;
  color: #f59e0b;
}

/* Modal Transitions */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-active .modal-container,
.modal-leave-active .modal-container {
  transition: transform 0.3s ease;
}

.modal-enter-from .modal-container,
.modal-leave-to .modal-container {
  transform: scale(0.95);
}

/* Responsive */
@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    gap: 12px;
    align-items: stretch;
  }

  .calendar-header {
    padding: 15px 20px;
    flex-direction: column;
    gap: 12px;
  }

  .calendar-nav {
    width: 100%;
    justify-content: center;
  }

  .calendar-grid {
    padding: 10px;
  }

  .day-cell {
    min-height: 80px;
    padding: 5px;
  }

  .event-item {
    font-size: 0.7rem;
    padding: 2px 4px;
  }

  .event-time {
    display: none;
  }

  .form-row {
    grid-template-columns: 1fr;
  }

  .debug-info {
    flex-direction: column;
    gap: 8px;
    align-items: stretch;
  }

  .agenda-list-item {
    padding: 12px;
  }

  .agenda-list-title {
    font-size: 1rem;
  }

  .status-actions {
    flex-direction: column;
  }

  .action-buttons {
    flex: 1;
    justify-content: flex-end;
  }

  .modal-container {
    max-height: 95vh;
  }

  .modal-header {
    padding: 16px 20px;
  }

  .modal-body {
    padding: 20px;
  }
}
</style>
