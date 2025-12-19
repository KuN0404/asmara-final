<template>
  <AdminLayout>
    <div class="page-header">
      <h2 class="page-title">Tambah Pengumuman</h2>
      <router-link to="/announcements" class="btn btn-secondary">← Kembali</router-link>
    </div>

    <div class="form-container">
      <form @submit.prevent="handleSubmit">
        <!-- Judul -->
        <div class="form-group">
          <label class="form-label">Judul Pengumuman *</label>
          <input
            type="text"
            class="form-input"
            v-model="form.title"
            placeholder="Masukkan judul pengumuman"
            required
          />
        </div>

        <!-- Editor Quill -->
        <div class="form-group">
          <label class="form-label">Isi Pengumuman *</label>
          <QuillEditor
            v-model:content="form.content"
            @update:content="(val) => (form.content = val)"
            content-type="html"
            theme="snow"
            toolbar="full"
            class="quill-editor"
          />
        </div>

        <!-- Link Lampiran (Primary) -->
        <div class="form-group">
          <label class="form-label">Link Lampiran (Arsip)</label>
          <div class="attachment-links-section">
            <div class="link-input-row" v-for="(link, index) in form.attachment_links" :key="index">
              <input
                type="url"
                class="form-input"
                v-model="form.attachment_links[index]"
                placeholder="https://drive.google.com/... atau link dokumen lainnya"
              />
              <button type="button" @click="removeLink(index)" class="remove-link-btn">✕</button>
            </div>
            <button type="button" @click="addLink" class="btn-add-link">
              🔗 Tambah Link Arsip
            </button>
          </div>
        </div>

        <!-- File Upload (Optional) -->
        <div class="form-group">
          <div class="file-upload-toggle">
            <button type="button" @click="showFileUpload = !showFileUpload" class="btn-toggle-upload">
              {{ showFileUpload ? '📁 Sembunyikan Upload File' : '📎 Upload File (Opsional)' }}
            </button>
          </div>
          
          <div v-if="showFileUpload" class="file-upload-area">
            <input
              type="file"
              class="form-input"
              multiple
              @change="handleAttachmentsUpload"
              accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"
            />
            <span class="upload-hint">Maks 10MB per file. Format: JPG, PNG, PDF, DOC</span>
          </div>
          
          <ul v-if="attachmentNames.length" class="attachment-list">
            <li v-for="(name, index) in attachmentNames" :key="index">
              📎 {{ name }}
              <button type="button" class="remove-file-btn" @click="removeAttachment(index)">
                ❌
              </button>
            </li>
          </ul>
        </div>

        <!-- Checkbox kirim notifikasi WA -->
        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" v-model="form.is_notification" class="checkbox-input" />
            <span>Kirim Notifikasi WhatsApp</span>
          </label>
        </div>

        <!-- Actions -->
        <div class="form-actions">
          <router-link to="/announcements" class="btn btn-secondary">Batal</router-link>
          <button type="submit" class="btn btn-primary" :disabled="loading">
            <span v-if="!loading">💾 Simpan</span>
            <span v-else>Menyimpan...</span>
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { useNotificationStore } from '@/stores/notification'
import announcementService from '@/services/announcementService'
import { handleError } from '@/utils/helpers'

// 🪄 Import Quill Editor
import { QuillEditor } from '@vueup/vue-quill'
import '@vueup/vue-quill/dist/vue-quill.snow.css'

const router = useRouter()
const notificationStore = useNotificationStore()

const loading = ref(false)
const attachmentNames = ref([])
const showFileUpload = ref(false)

const form = ref({
  title: '',
  content: '',
  attachment_links: [],
  attachments: [],
  is_notification: false,
})

// Add/remove link functions
const addLink = () => {
  form.value.attachment_links.push('')
}

const removeLink = (index) => {
  form.value.attachment_links.splice(index, 1)
}

/** 📎 Upload banyak lampiran */
const handleAttachmentsUpload = (e) => {
  const files = Array.from(e.target.files)
  if (files.length > 0) {
    form.value.attachments.push(...files)
    attachmentNames.value.push(...files.map((f) => f.name))
  }
}

/** 🗑️ Hapus file tertentu */
const removeAttachment = (index) => {
  form.value.attachments.splice(index, 1)
  attachmentNames.value.splice(index, 1)
}

const handleSubmit = async () => {
  if (!form.value.title.trim() || !form.value.content || form.value.content === '<p><br></p>') {
    notificationStore.error('Judul dan isi wajib diisi ❌')
    return
  }

  const formData = new FormData()
  formData.append('title', form.value.title)
  formData.append('content', form.value.content)
  formData.append('is_notification', form.value.is_notification ? '1' : '0')

  // Add attachment links
  form.value.attachment_links.filter(link => link && link.trim()).forEach((link) => {
    formData.append('attachment_links[]', link)
  })

  form.value.attachments.forEach((file) => {
    formData.append('attachments[]', file)
  })

  loading.value = true
  try {
    await announcementService.create(formData)
    notificationStore.success('Pengumuman berhasil ditambahkan ✅')
    router.push('/announcements')
  } catch (error) {
    notificationStore.error(handleError(error))
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.form-container {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  margin-top: 1rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  max-width: 750px;
  margin-left: auto;
  margin-right: auto;
}

.form-group {
  margin-bottom: 1.25rem;
}

.form-label {
  display: block;
  font-weight: 600;
  margin-bottom: 6px;
  color: #444;
}

.form-input {
  width: 100%;
  padding: 12px;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 14px;
}

/* 🧭 Atur Quill Editor */
.quill-editor .ql-container {
  min-height: 300px;
}
.quill-editor .ql-editor {
  min-height: 300px;
  padding-bottom: 50px;
}
.quill-editor {
  position: relative;
  z-index: 1;
}

.attachment-list {
  list-style: none;
  padding-left: 0;
}

.attachment-list li {
  margin-top: 6px;
  font-size: 13px;
  color: #555;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.remove-file-btn {
  background: transparent;
  border: none;
  color: red;
  font-size: 14px;
  cursor: pointer;
  margin-left: 8px;
}

/* Link attachment styles */
.attachment-links-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.link-input-row {
  display: flex;
  gap: 8px;
  align-items: center;
}

.link-input-row .form-input {
  flex: 1;
}

.remove-link-btn {
  background: #fee2e2;
  color: #dc2626;
  border: none;
  width: 32px;
  height: 32px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s;
}

.remove-link-btn:hover {
  background: #fecaca;
}

.btn-add-link {
  background: #eff6ff;
  color: #1e40af;
  border: 1px dashed #3b82f6;
  padding: 10px 16px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 13px;
  transition: all 0.2s;
  margin-top: 4px;
}

.btn-add-link:hover {
  background: #dbeafe;
}

/* File upload toggle */
.file-upload-toggle {
  margin-bottom: 12px;
}

.btn-toggle-upload {
  background: #f3f4f6;
  color: #4b5563;
  border: 1px solid #d1d5db;
  padding: 10px 16px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 13px;
  transition: all 0.2s;
}

.btn-toggle-upload:hover {
  background: #e5e7eb;
}

.file-upload-area {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px;
  background: #f9fafb;
  border: 1px dashed #d1d5db;
  border-radius: 8px;
  margin-bottom: 12px;
}

.upload-hint {
  font-size: 12px;
  color: #6b7280;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
}

.checkbox-input {
  width: 20px;
  height: 20px;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 20px;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 10px 18px;
  font-size: 14px;
  border-radius: 8px;
  cursor: pointer;
  text-decoration: none;
  border: none;
  transition: 0.2s;
}

.btn-primary {
  background-color: #1976d2;
  color: #fff;
}

.btn-primary:hover {
  background-color: #125ea8;
}

.btn-secondary {
  background-color: #f1f1f1;
  color: #444;
}

.btn-secondary:hover {
  background-color: #e1e1e1;
}
</style>
