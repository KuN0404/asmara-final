import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/Login.vue'),
    meta: { layout: 'auth' },
  },
  {
    path: '/',
    name: 'Dashboard',
    component: () => import('@/views/Dashboard.vue'),
    meta: { requiresAuth: true, layout: 'admin' },
  },
  {
    path: '/office-agenda',
    name: 'OfficeAgendaIndex',
    component: () => import('@/views/office-agenda/Index.vue'),
    meta: { requiresAuth: true, layout: 'admin', roles: ['super_admin', 'kepala', 'ketua_tim', 'kasubbag', 'staff'] },
  },
  // {
  //   path: '/office-agenda/create',
  //   name: 'OfficeAgendaCreate',
  //   component: () => import('@/views/office-agenda/Create.vue'),
  //   meta: { requiresAuth: true, layout: 'admin', roles: ['super_admin', 'admin'] },
  // },
  // {
  //   path: '/office-agenda/:id/edit',
  //   name: 'OfficeAgendaEdit',
  //   component: () => import('@/views/office-agenda/Edit.vue'),
  //   meta: { requiresAuth: true, layout: 'admin', roles: ['super_admin', 'admin'] },
  // },
  // {
  //   path: '/office-agenda/:id',
  //   name: 'OfficeAgendaDetail',
  //   component: () => import('@/views/office-agenda/Detail.vue'),
  //   meta: { requiresAuth: true, layout: 'admin' },
  // },
  {
    path: '/my-agenda',
    name: 'MyAgendaIndex',
    component: () => import('@/views/my-agenda/Index.vue'),
    meta: { requiresAuth: true, layout: 'admin' },
  },
  // {
  //   path: '/my-agenda/create',
  //   name: 'MyAgendaCreate',
  //   component: () => import('@/views/my-agenda/Create.vue'),
  //   meta: { requiresAuth: true, layout: 'admin' },
  // },
  // {
  //   path: '/my-agenda/:id/edit',
  //   name: 'MyAgendaEdit',
  //   component: () => import('@/views/my-agenda/Edit.vue'),
  //   meta: { requiresAuth: true, layout: 'admin' },
  // },
  {
    path: '/announcements',
    name: 'AnnouncementIndex',
    component: () => import('@/views/announcement/Index.vue'),
    meta: { requiresAuth: true, layout: 'admin' },
  },
  {
    path: '/announcements/:id',
    name: 'AnnouncementDetail',
    component: () => import('@/views/announcement/Detail.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/announcements/create',
    name: 'AnnouncementCreate',
    component: () => import('@/views/announcement/Create.vue'),
    meta: { requiresAuth: true, layout: 'admin', roles: ['super_admin', 'kepala', 'ketua_tim', 'kasubbag'] },
  },
  {
    path: '/announcements/:id/edit',
    name: 'AnnouncementEdit',
    component: () => import('@/views/announcement/Edit.vue'),
    meta: { requiresAuth: true, layout: 'admin', roles: ['super_admin', 'kepala', 'ketua_tim', 'kasubbag'] },
  },
  {
    path: '/users',
    name: 'UserIndex',
    component: () => import('@/views/user/Index.vue'),
    meta: { requiresAuth: true, layout: 'admin', roles: ['super_admin', 'kepala', 'ketua_tim', 'kasubbag'] },
  },
  {
    path: '/users/:id',
    name: 'UsersDetail',
    component: () => import('@/views/user/Detail.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/users/create',
    name: 'UserCreate',
    component: () => import('@/views/user/Create.vue'),
    meta: { requiresAuth: true, layout: 'admin', roles: ['super_admin', 'kepala', 'ketua_tim', 'kasubbag'] },
  },
  {
    path: '/users/:id/edit',
    name: 'UserEdit',
    component: () => import('@/views/user/Edit.vue'),
    meta: { requiresAuth: true, layout: 'admin', roles: ['super_admin', 'kepala', 'ketua_tim', 'kasubbag'] },
  },
  {
    path: '/rooms',
    name: 'RoomIndex',
    component: () => import('@/views/room/Index.vue'),
    meta: { requiresAuth: true, layout: 'admin' },
  },
  {
    path: '/rooms/:id',
    name: 'RoomDetail',
    component: () => import('@/views/room/Detail.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/rooms/create',
    name: 'RoomCreate',
    component: () => import('@/views/room/Create.vue'),
    meta: { requiresAuth: true, layout: 'admin', roles: ['super_admin', 'kepala', 'ketua_tim', 'kasubbag'] },
  },
  {
    path: '/rooms/:id/edit',
    name: 'RoomEdit',
    component: () => import('@/views/room/Edit.vue'),
    meta: { requiresAuth: true, layout: 'admin', roles: ['super_admin', 'kepala', 'ketua_tim', 'kasubbag'] },
  },
  {
    path: '/participants',
    name: 'ParticipantIndex',
    component: () => import('@/views/participant/Index.vue'),
    meta: { requiresAuth: true, layout: 'admin' },
  },
  {
    path: '/participants/:id',
    name: 'ParticipantDetail',
    component: () => import('@/views/participant/Detail.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/participants/create',
    name: 'ParticipantCreate',
    component: () => import('@/views/participant/Create.vue'),
    meta: { requiresAuth: true, layout: 'admin', roles: ['super_admin', 'kepala', 'ketua_tim', 'kasubbag'] },
  },
  {
    path: '/participants/:id/edit',
    name: 'ParticipantEdit',
    component: () => import('@/views/participant/Edit.vue'),
    meta: { requiresAuth: true, layout: 'admin', roles: ['super_admin', 'kepala', 'ketua_tim', 'kasubbag'] },
  },
  {
    path: '/profile',
    name: 'Profile',
    component: () => import('@/views/Profile.vue'),
    meta: { requiresAuth: true, layout: 'admin' },
  },
  {
    path: '/whatsapp',
    name: 'WhatsAppManagement',
    component: () => import('@/views/WhatsAppManagement.vue'),
    meta: { requiresAuth: true, layout: 'admin', roles: ['super_admin', 'kepala', 'ketua_tim', 'kasubbag'] },
  },
]

const router = createRouter({
  history: createWebHistory('/asmara/'),
  routes,
})

// Flag to track if initial auth check is done
let isAuthChecked = false

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  // Jika ada token tapi user belum diload, panggil checkAuth dulu
  if (authStore.token && !authStore.user && !isAuthChecked) {
    await authStore.checkAuth()
    isAuthChecked = true
  }

  // Cek autentikasi
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next('/login')
  } else if (to.path === '/login' && authStore.isAuthenticated) {
    next('/')
  } else if (to.meta.roles && !to.meta.roles.some((role) => authStore.hasRole(role))) {
    // Role check - hanya redirect jika user sudah diload
    next('/')
  } else {
    next()
  }
})

export default router

