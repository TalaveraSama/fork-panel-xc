import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@stores/auth'

// Layouts
import AdminLayout from '@layouts/AdminLayout.vue'
import AuthLayout from '@layouts/AuthLayout.vue'

// Auth Views
import LoginView from '@views/auth/LoginView.vue'
import ForgotPasswordView from '@views/auth/ForgotPasswordView.vue'
import TwoFactorView from '@views/auth/TwoFactorView.vue'

// Dashboard
import DashboardView from '@views/dashboard/DashboardView.vue'

// Streams
import StreamsView from '@views/streams/StreamsView.vue'
import StreamDetailView from '@views/streams/StreamDetailView.vue'
import StreamCreateView from '@views/streams/StreamCreateView.vue'
import StreamEditView from '@views/streams/StreamEditView.vue'

// Movies/VOD
import MoviesView from '@views/vod/MoviesView.vue'
import MovieDetailView from '@views/vod/MovieDetailView.vue'
import MovieCreateView from '@views/vod/MovieCreateView.vue'
import SeriesView from '@views/vod/SeriesView.vue'
import SeriesDetailView from '@views/vod/SeriesDetailView.vue'
import SeriesCreateView from '@views/vod/SeriesCreateView.vue'

// Users & Lines
import UsersView from '@views/users/UsersView.vue'
import UserDetailView from '@views/users/UserDetailView.vue'
import UserCreateView from '@views/users/UserCreateView.vue'
import LinesView from '@views/users/LinesView.vue'
import LineDetailView from '@views/users/LineDetailView.vue'

// Resellers
import ResellersView from '@views/resellers/ResellersView.vue'
import ResellerDetailView from '@views/resellers/ResellerDetailView.vue'
import ResellerCreateView from '@views/resellers/ResellerCreateView.vue'

// EPG
import EpgView from '@views/epg/EpgView.vue'
import EpgSourcesView from '@views/epg/EpgSourcesView.vue'
import EpgChannelsView from '@views/epg/EpgChannelsView.vue'

// Bouquets
import BouquetsView from '@views/bouquets/BouquetsView.vue'
import BouquetDetailView from '@views/bouquets/BouquetDetailView.vue'

// Categories
import CategoriesView from '@views/categories/CategoriesView.vue'

// Settings
import SettingsView from '@views/settings/SettingsView.vue'
import SettingsGeneralView from '@views/settings/SettingsGeneralView.vue'
import SettingsStreamingView from '@views/settings/SettingsStreamingView.vue'
import SettingsAppearanceView from '@views/settings/SettingsAppearanceView.vue'
import SettingsSecurityView from '@views/settings/SettingsSecurityView.vue'
import SettingsClusterView from '@views/settings/SettingsClusterView.vue'
import SettingsModulesView from '@views/settings/SettingsModulesView.vue'
import SettingsBackupView from '@views/settings/SettingsBackupView.vue'
import SettingsLogsView from '@views/settings/SettingsLogsView.vue'

// Tools
import ToolsView from '@views/tools/ToolsView.vue'
import ToolsDiagnosticsView from '@views/tools/ToolsDiagnosticsView.vue'
import ToolsDatabaseView from '@views/tools/ToolsDatabaseView.vue'
import ToolsCacheView from '@views/tools/ToolsCacheView.vue'

// Modules
import ModulesView from '@views/modules/ModulesView.vue'
import ModuleDetailView from '@views/modules/ModuleDetailView.vue'

// Profile
import ProfileView from '@views/profile/ProfileView.vue'

// 404
import NotFoundView from '@views/errors/NotFoundView.vue'

const routes = [
  {
    path: '/login',
    component: AuthLayout,
    meta: { public: true },
    children: [
      { path: '', name: 'login', component: LoginView },
      { path: 'forgot-password', name: 'forgot-password', component: ForgotPasswordView },
      { path: '2fa', name: '2fa', component: TwoFactorView },
    ],
  },
  {
    path: '/',
    component: AdminLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'dashboard', component: DashboardView },

      // Streams
      {
        path: 'streams',
        children: [
          { path: '', name: 'streams', component: StreamsView },
          { path: 'create', name: 'stream-create', component: StreamCreateView },
          { path: ':id', name: 'stream-detail', component: StreamDetailView, props: true },
          { path: ':id/edit', name: 'stream-edit', component: StreamEditView, props: true },
        ],
      },

      // Movies/VOD
      {
        path: 'movies',
        children: [
          { path: '', name: 'movies', component: MoviesView },
          { path: 'create', name: 'movie-create', component: MovieCreateView },
          { path: ':id', name: 'movie-detail', component: MovieDetailView, props: true },
        ],
      },
      {
        path: 'series',
        children: [
          { path: '', name: 'series', component: SeriesView },
          { path: 'create', name: 'series-create', component: SeriesCreateView },
          { path: ':id', name: 'series-detail', component: SeriesDetailView, props: true },
        ],
      },

      // Users & Lines
      {
        path: 'users',
        children: [
          { path: '', name: 'users', component: UsersView },
          { path: 'create', name: 'user-create', component: UserCreateView },
          { path: ':id', name: 'user-detail', component: UserDetailView, props: true },
        ],
      },
      {
        path: 'lines',
        children: [
          { path: '', name: 'lines', component: LinesView },
          { path: ':id', name: 'line-detail', component: LineDetailView, props: true },
        ],
      },

      // Resellers
      {
        path: 'resellers',
        children: [
          { path: '', name: 'resellers', component: ResellersView },
          { path: 'create', name: 'reseller-create', component: ResellerCreateView },
          { path: ':id', name: 'reseller-detail', component: ResellerDetailView, props: true },
        ],
      },

      // EPG
      {
        path: 'epg',
        children: [
          { path: '', name: 'epg', component: EpgView },
          { path: 'sources', name: 'epg-sources', component: EpgSourcesView },
          { path: 'channels', name: 'epg-channels', component: EpgChannelsView },
        ],
      },

      // Bouquets
      {
        path: 'bouquets',
        children: [
          { path: '', name: 'bouquets', component: BouquetsView },
          { path: ':id', name: 'bouquet-detail', component: BouquetDetailView, props: true },
        ],
      },

      // Categories
      { path: 'categories', name: 'categories', component: CategoriesView },

      // Settings
      {
        path: 'settings',
        component: SettingsView,
        children: [
          { path: '', redirect: { name: 'settings-general' } },
          { path: 'general', name: 'settings-general', component: SettingsGeneralView },
          { path: 'streaming', name: 'settings-streaming', component: SettingsStreamingView },
          { path: 'appearance', name: 'settings-appearance', component: SettingsAppearanceView },
          { path: 'security', name: 'settings-security', component: SettingsSecurityView },
          { path: 'cluster', name: 'settings-cluster', component: SettingsClusterView },
          { path: 'modules', name: 'settings-modules', component: SettingsModulesView },
          { path: 'backup', name: 'settings-backup', component: SettingsBackupView },
          { path: 'logs', name: 'settings-logs', component: SettingsLogsView },
        ],
      },

      // Tools
      {
        path: 'tools',
        component: ToolsView,
        children: [
          { path: '', redirect: { name: 'tools-diagnostics' } },
          { path: 'diagnostics', name: 'tools-diagnostics', component: ToolsDiagnosticsView },
          { path: 'database', name: 'tools-database', component: ToolsDatabaseView },
          { path: 'cache', name: 'tools-cache', component: ToolsCacheView },
        ],
      },

      // Modules
      {
        path: 'modules',
        children: [
          { path: '', name: 'modules', component: ModulesView },
          { path: ':id', name: 'module-detail', component: ModuleDetailView, props: true },
        ],
      },

      // Profile
      { path: 'profile', name: 'profile', component: ProfileView },
    ],
  },

  // 404
  { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundView },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    } else {
      return { top: 0 }
    }
  },
})

// Navigation guards
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  // Initialize auth if not done
  if (!authStore.initialized) {
    await authStore.initAuth()
  }

  // Public routes
  if (to.meta.public) {
    if (authStore.isAuthenticated && to.name === 'login') {
      return next({ name: 'dashboard' })
    }
    return next()
  }

  // Protected routes
  if (to.meta.requiresAuth) {
    if (!authStore.isAuthenticated) {
      return next({ name: 'login', query: { redirect: to.fullPath } })
    }

    // Check permissions if required
    if (to.meta.permissions) {
      const hasPermission = authStore.hasPermissions(to.meta.permissions)
      if (!hasPermission) {
        return next({ name: 'dashboard' })
      }
    }
  }

  next()
})

export default router