<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div 
        v-if="isOpen" 
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/60 dark:bg-black/80 backdrop-blur-xs select-none"
        @click.self="handleClose"
      >
        <div 
          class="w-full max-w-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh] transition-colors duration-200"
          role="dialog"
          aria-modal="true"
          aria-labelledby="profile-modal-title"
        >
          <!-- Cabecera del Perfil con Avatar -->
          <div class="p-4 sm:p-5 bg-gradient-to-b from-slate-50 to-white dark:from-slate-950/90 dark:to-slate-900 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3 sm:gap-3.5">
              <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-gradient-to-tr from-sky-500 to-indigo-600 flex items-center justify-center text-white text-sm sm:text-base font-extrabold shadow-md shadow-sky-500/25 shrink-0">
                {{ userInitials }}
              </div>
              <div>
                <h3 id="profile-modal-title" class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">
                  {{ authStore.user?.name || 'Agente de Ventas' }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5">
                  {{ authStore.user?.email }}
                </p>
              </div>
            </div>

            <button 
              type="button"
              @click="handleClose"
              class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
              title="Cerrar ventana"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <!-- Selector de Pestañas: Mi Perfil / Seguridad -->
          <div class="px-4 sm:px-5 border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/40 flex items-center gap-4 shrink-0">
            <button 
              type="button"
              @click="activeTab = 'info'"
              :class="[
                'py-2.5 text-xs font-semibold border-b-2 transition-all flex items-center gap-2 cursor-pointer',
                activeTab === 'info' 
                  ? 'border-sky-500 text-sky-600 dark:text-sky-400' 
                  : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'
              ]"
            >
              <User class="w-3.5 h-3.5" />
              <span>Perfil y Ajustes</span>
            </button>

            <button 
              type="button"
              @click="activeTab = 'password'"
              :class="[
                'py-2.5 text-xs font-semibold border-b-2 transition-all flex items-center gap-2 cursor-pointer',
                activeTab === 'password' 
                  ? 'border-sky-500 text-sky-600 dark:text-sky-400' 
                  : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'
              ]"
            >
              <KeyRound class="w-3.5 h-3.5" />
              <span>Cambiar Contraseña</span>
            </button>
          </div>

          <!-- PESTAÑA 1: PERFIL, AJUSTES, TEMA Y NOTIFICACIONES -->
          <div v-if="activeTab === 'info'" class="p-4 sm:p-5 space-y-3.5 text-xs overflow-y-auto select-text">
            
            <!-- Opción: Alternar Panel Admin / Consola de Chats (Solo visible para Administradores) -->
            <div 
              v-if="authStore.isAdmin" 
              class="p-3.5 rounded-xl bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent dark:from-amber-500/15 border border-amber-500/30 flex items-center justify-between gap-3 shadow-2xs"
            >
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-amber-500/20 text-amber-700 dark:text-amber-300 flex items-center justify-center shrink-0">
                  <BarChart3 v-if="!isInAdmin" class="w-4 h-4" />
                  <Headphones v-else class="w-4 h-4" />
                </div>
                <div>
                  <div class="font-bold text-slate-900 dark:text-white text-xs">
                    {{ isInAdmin ? 'Consola de Atención de Chats' : 'Panel de Administración' }}
                  </div>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400">
                    {{ isInAdmin ? 'Volver a atender conversaciones en vivo de WhatsApp' : 'Supervisión de métricas, reportes, pasajeros y agentes' }}
                  </p>
                </div>
              </div>

              <button 
                type="button"
                @click="toggleDashboardView"
                class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs shadow-xs transition-all cursor-pointer shrink-0 flex items-center gap-1.5"
              >
                <span>{{ isInAdmin ? 'Ir a Chats' : 'Ir a Panel' }}</span>
                <ChevronRight class="w-3.5 h-3.5" />
              </button>
            </div>

            <!-- Ajuste: Tema de la Aplicación (Modo Claro / Modo Oscuro) -->
            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-slate-200/70 dark:bg-slate-800 flex items-center justify-center text-slate-700 dark:text-slate-200 shrink-0">
                  <Sun v-if="!themeStore.isDark" class="w-4 h-4 text-amber-500" />
                  <Moon v-else class="w-4 h-4 text-sky-400" />
                </div>
                <div>
                  <div class="font-bold text-slate-900 dark:text-white text-xs flex items-center gap-1.5">
                    Tema Visual
                    <span class="text-[10px] font-normal text-slate-500 dark:text-slate-400">
                      ({{ themeStore.isDark ? 'Modo Oscuro' : 'Modo Claro' }})
                    </span>
                  </div>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400">
                    Alterna la interfaz entre tema claro y oscuro
                  </p>
                </div>
              </div>

              <!-- Selector Segmentado Claro / Oscuro -->
              <div class="inline-flex p-0.5 rounded-xl bg-slate-200/80 dark:bg-slate-800 border border-slate-300 dark:border-slate-700">
                <button 
                  type="button"
                  @click="themeStore.setTheme('light')"
                  :class="[
                    'px-2.5 py-1 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5 cursor-pointer',
                    !themeStore.isDark 
                      ? 'bg-white text-slate-900 shadow-xs' 
                      : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                  ]"
                  title="Activar Modo Claro"
                >
                  <Sun class="w-3.5 h-3.5 text-amber-500" />
                  <span class="hidden xs:inline">Claro</span>
                </button>
                <button 
                  type="button"
                  @click="themeStore.setTheme('dark')"
                  :class="[
                    'px-2.5 py-1 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5 cursor-pointer',
                    themeStore.isDark 
                      ? 'bg-slate-900 text-white shadow-xs' 
                      : 'text-slate-600 hover:text-slate-900'
                  ]"
                  title="Activar Modo Oscuro"
                >
                  <Moon class="w-3.5 h-3.5 text-sky-400" />
                  <span class="hidden xs:inline">Oscuro</span>
                </button>
              </div>
            </div>

            <!-- Ajuste: Notificaciones de Escritorio y Sonido -->
            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3">
              <div class="flex items-center gap-3">
                <div :class="[
                  'w-8 h-8 rounded-lg flex items-center justify-center shrink-0',
                  notificationPermission === 'granted'
                    ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                    : notificationPermission === 'denied'
                      ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400'
                      : 'bg-amber-500/15 text-amber-600 dark:text-amber-400'
                ]">
                  <BellRing v-if="notificationPermission === 'granted'" class="w-4 h-4" />
                  <BellOff v-else-if="notificationPermission === 'denied'" class="w-4 h-4" />
                  <Bell v-else class="w-4 h-4" />
                </div>
                <div>
                  <div class="font-bold text-slate-900 dark:text-white text-xs flex items-center gap-1.5">
                    Notificaciones y Alertas
                    <span :class="[
                      'text-[10px] font-semibold px-1.5 py-0.2 rounded-full',
                      notificationPermission === 'granted' 
                        ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' 
                        : notificationPermission === 'denied'
                          ? 'bg-rose-500/15 text-rose-700 dark:text-rose-300'
                          : 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
                    ]">
                      {{ notificationPermission === 'granted' ? 'Activas' : (notificationPermission === 'denied' ? 'Bloqueadas' : 'Desactivadas') }}
                    </span>
                  </div>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400">
                    Avisos sonoros y en segundo plano al recibir mensajes
                  </p>
                </div>
              </div>

              <button 
                type="button"
                @click="handleNotificationClick"
                :class="[
                  'px-3 py-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer shrink-0 flex items-center gap-1.5 shadow-2xs',
                  notificationPermission === 'granted'
                    ? 'bg-emerald-500/10 hover:bg-emerald-500/20 border-emerald-500/30 text-emerald-700 dark:text-emerald-300'
                    : notificationPermission === 'denied'
                      ? 'bg-slate-100 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-400 cursor-not-allowed'
                      : 'bg-sky-600 hover:bg-sky-500 border-sky-600 text-white shadow-xs'
                ]"
                :title="notificationPermission === 'granted' ? 'Enviar alerta de prueba' : 'Activar notificaciones'"
              >
                <span>{{ notificationPermission === 'granted' ? 'Probar Alerta' : (notificationPermission === 'denied' ? 'Bloqueadas' : 'Activar') }}</span>
              </button>
            </div>

            <!-- Ajuste Opcional: Instalar Aplicación PWA (si el navegador lo permite) -->
            <div 
              v-if="canInstall" 
              class="p-3.5 rounded-xl bg-sky-500/10 dark:bg-sky-500/15 border border-sky-500/30 flex items-center justify-between gap-3"
            >
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-sky-500/20 text-sky-600 dark:text-sky-300 flex items-center justify-center shrink-0">
                  <Download class="w-4 h-4" />
                </div>
                <div>
                  <div class="font-bold text-slate-900 dark:text-white text-xs">
                    Instalar Aplicación en este Dispositivo
                  </div>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400">
                    Acceso directo desde el escritorio o celular sin barra de navegador
                  </p>
                </div>
              </div>

              <button 
                type="button"
                @click="promptInstall"
                class="px-3 py-1.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-xs transition-colors cursor-pointer shrink-0"
              >
                Instalar App
              </button>
            </div>

            <!-- Ficha de Datos Institucionales de Cuenta -->
            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-slate-500 dark:text-slate-400 text-[11px] flex items-center gap-1.5">
                  <Mail class="w-3.5 h-3.5 text-slate-400" />
                  Correo Institucional
                </span>
                <span class="font-mono font-medium text-slate-800 dark:text-slate-200">
                  {{ authStore.user?.email }}
                </span>
              </div>

              <div class="flex items-center justify-between pt-2 border-t border-slate-200/70 dark:border-slate-800/70">
                <span class="text-slate-500 dark:text-slate-400 text-[11px] flex items-center gap-1.5">
                  <ShieldCheck class="w-3.5 h-3.5 text-slate-400" />
                  Rol Operativo
                </span>
                <span class="font-medium text-slate-800 dark:text-slate-200">
                  {{ authStore.isAdmin ? 'Administrador General' : 'Agente de Ventas' }}
                </span>
              </div>

              <div class="flex items-center justify-between pt-2 border-t border-slate-200/70 dark:border-slate-800/70">
                <span class="text-slate-500 dark:text-slate-400 text-[11px] flex items-center gap-1.5">
                  <BadgeCheck class="w-3.5 h-3.5 text-slate-400" />
                  Estado de Acceso
                </span>
                <span class="font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                  <CheckCircle2 class="w-3.5 h-3.5" />
                  Habilitado y Verificado
                </span>
              </div>
            </div>

            <!-- Acciones al pie: Cerrar Sesión y Cerrar Modal -->
            <div class="pt-2 flex items-center justify-between border-t border-slate-200 dark:border-slate-800">
              <button 
                type="button"
                @click="handleLogoutFromModal"
                class="px-3 py-2 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-600 dark:text-red-400 border border-red-500/30 font-semibold text-xs flex items-center gap-1.5 transition-colors cursor-pointer"
              >
                <LogOut class="w-3.5 h-3.5" />
                <span>Cerrar Sesión</span>
              </button>

              <button 
                type="button"
                @click="handleClose"
                class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-medium transition-colors cursor-pointer"
              >
                Cerrar
              </button>
            </div>
          </div>

          <!-- PESTAÑA 2: CAMBIAR CONTRASEÑA -->
          <div v-else class="p-4 sm:p-5 space-y-4 text-xs overflow-y-auto select-text">
            <!-- Mensaje de Éxito -->
            <div 
              v-if="successMessage" 
              class="p-3.5 rounded-xl bg-emerald-500/10 dark:bg-emerald-500/15 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 flex items-start gap-2.5 animate-fadeIn"
            >
              <CheckCircle2 class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
              <div class="flex-1">
                <p class="font-semibold">{{ successMessage }}</p>
                <p class="text-[11px] text-emerald-600/80 dark:text-emerald-400/80 mt-0.5">
                  Las credenciales se actualizaron con éxito.
                </p>
              </div>
            </div>

            <!-- Mensaje de Error -->
            <div 
              v-if="errorMessage" 
              class="p-3.5 rounded-xl bg-red-500/10 dark:bg-red-500/15 border border-red-500/30 text-red-700 dark:text-red-300 flex items-start gap-2.5 animate-fadeIn"
            >
              <AlertCircle class="w-4 h-4 text-red-500 shrink-0 mt-0.5" />
              <div class="flex-1">
                <p class="font-semibold">{{ errorMessage }}</p>
              </div>
              <button 
                type="button" 
                @click="errorMessage = ''" 
                class="text-red-400 hover:text-red-600 dark:hover:text-red-200 cursor-pointer"
              >
                <X class="w-3.5 h-3.5" />
              </button>
            </div>

            <form @submit.prevent="handleSubmit" class="space-y-4">
              <!-- Campo: Contraseña Actual -->
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                  Contraseña Actual
                </label>
                <div class="relative">
                  <input 
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    :type="showCurrentPassword ? 'text' : 'password'"
                    required
                    autocomplete="current-password"
                    placeholder="Ingresa tu contraseña actual"
                    class="w-full px-3 py-2.5 pr-10 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white text-xs placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all"
                  />
                  <button 
                    type="button"
                    @click="showCurrentPassword = !showCurrentPassword"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
                    tabindex="-1"
                  >
                    <EyeOff v-if="showCurrentPassword" class="w-4 h-4" />
                    <Eye v-else class="w-4 h-4" />
                  </button>
                </div>
              </div>

              <!-- Campo: Nueva Contraseña -->
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                  Nueva Contraseña
                </label>
                <div class="relative">
                  <input 
                    v-model="form.password"
                    :type="showNewPassword ? 'text' : 'password'"
                    required
                    minlength="6"
                    autocomplete="new-password"
                    placeholder="Mínimo 6 caracteres"
                    class="w-full px-3 py-2.5 pr-10 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white text-xs placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all"
                  />
                  <button 
                    type="button"
                    @click="showNewPassword = !showNewPassword"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
                    tabindex="-1"
                  >
                    <EyeOff v-if="showNewPassword" class="w-4 h-4" />
                    <Eye v-else class="w-4 h-4" />
                  </button>
                </div>

                <!-- Barra visual de Fortaleza -->
                <div v-if="form.password" class="mt-2 space-y-1.5">
                  <div class="flex items-center justify-between text-[11px]">
                    <span class="text-slate-500 dark:text-slate-400">Seguridad:</span>
                    <span class="font-bold" :class="strengthInfo.colorClass">
                      {{ strengthInfo.label }}
                    </span>
                  </div>
                  <div class="grid grid-cols-4 gap-1.5 h-1.5 w-full">
                    <div 
                      v-for="idx in 4" 
                      :key="idx"
                      class="rounded-full transition-all duration-300"
                      :class="idx <= strengthScore ? strengthInfo.barClass : 'bg-slate-200 dark:bg-slate-800'"
                    ></div>
                  </div>
                </div>
              </div>

              <!-- Campo: Confirmar Nueva Contraseña -->
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                  Confirmar Nueva Contraseña
                </label>
                <div class="relative">
                  <input 
                    v-model="form.password_confirmation"
                    :type="showConfirmPassword ? 'text' : 'password'"
                    required
                    minlength="6"
                    autocomplete="new-password"
                    placeholder="Repite la nueva contraseña"
                    class="w-full px-3 py-2.5 pr-10 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white text-xs placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all"
                  />
                  <button 
                    type="button"
                    @click="showConfirmPassword = !showConfirmPassword"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
                    tabindex="-1"
                  >
                    <EyeOff v-if="showConfirmPassword" class="w-4 h-4" />
                    <Eye v-else class="w-4 h-4" />
                  </button>
                </div>
              </div>

              <!-- Lista Dinámica de Requisitos -->
              <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/40 border border-slate-200 dark:border-slate-800/80 space-y-1.5 text-[11px]">
                <div class="flex items-center gap-2" :class="hasMinLength ? 'text-emerald-600 dark:text-emerald-400 font-medium' : 'text-slate-400 dark:text-slate-500'">
                  <CheckCircle2 v-if="hasMinLength" class="w-3.5 h-3.5 shrink-0" />
                  <Circle v-else class="w-3.5 h-3.5 shrink-0" />
                  <span>Al menos 6 caracteres</span>
                </div>

                <div class="flex items-center gap-2" :class="passwordsMatch ? 'text-emerald-600 dark:text-emerald-400 font-medium' : 'text-slate-400 dark:text-slate-500'">
                  <CheckCircle2 v-if="passwordsMatch" class="w-3.5 h-3.5 shrink-0" />
                  <Circle v-else class="w-3.5 h-3.5 shrink-0" />
                  <span>Las contraseñas coinciden</span>
                </div>

                <div class="flex items-center gap-2" :class="isDifferentFromCurrent ? 'text-emerald-600 dark:text-emerald-400 font-medium' : 'text-slate-400 dark:text-slate-500'">
                  <CheckCircle2 v-if="isDifferentFromCurrent" class="w-3.5 h-3.5 shrink-0" />
                  <Circle v-else class="w-3.5 h-3.5 shrink-0" />
                  <span>Diferente a la contraseña actual</span>
                </div>
              </div>

              <!-- Aviso de Seguridad Institucional -->
              <div class="flex items-start gap-2 p-2.5 rounded-xl bg-sky-500/5 dark:bg-sky-500/10 border border-sky-500/20 text-[11px] text-sky-800 dark:text-sky-300 leading-relaxed">
                <ShieldCheck class="w-4 h-4 shrink-0 text-sky-600 dark:text-sky-400 mt-0.5" />
                <span>
                  Al cambiar tu clave, cualquier otra sesión abierta en otros dispositivos se cerrará automáticamente.
                </span>
              </div>

              <!-- Botones de Acción -->
              <div class="pt-2 flex items-center justify-end gap-2 border-t border-slate-200 dark:border-slate-800">
                <button 
                  type="button"
                  @click="handleClose"
                  :disabled="loading"
                  class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-medium transition-colors cursor-pointer disabled:opacity-50"
                >
                  Cancelar
                </button>

                <button 
                  type="submit"
                  :disabled="loading || !canSubmit"
                  class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold shadow-md shadow-sky-600/30 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all cursor-pointer"
                >
                  <Loader2 v-if="loading" class="w-3.5 h-3.5 animate-spin" />
                  <KeyRound v-else class="w-3.5 h-3.5" />
                  <span>{{ loading ? 'Actualizando...' : 'Actualizar Contraseña' }}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, reactive, computed, watch, nextTick } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/useAuthStore';
import { useThemeStore } from '@/stores/useThemeStore';
import { 
  canInstall, 
  promptInstall, 
  notificationPermission, 
  requestNotificationPermission, 
  showSystemNotification,
  sendTestPushNotification
} from '@/services/pwaService';
import { 
  User,
  KeyRound, 
  X, 
  Eye, 
  EyeOff, 
  CheckCircle2, 
  AlertCircle, 
  Circle, 
  ShieldCheck, 
  Loader2,
  Mail,
  BadgeCheck,
  Sun,
  Moon,
  Bell,
  BellRing,
  BellOff,
  BarChart3,
  Headphones,
  Download,
  LogOut,
  ChevronRight
} from 'lucide-vue-next';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  initialTab: {
    type: String,
    default: 'info',
  },
});

const emit = defineEmits(['close', 'updated']);

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const themeStore = useThemeStore();

const activeTab = ref('info');
const currentPasswordInput = ref(null);

const form = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

const loading = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

const isInAdmin = computed(() => {
  return route.path.startsWith('/admin');
});

const userInitials = computed(() => {
  const name = authStore.user?.name || '';
  if (!name) return 'AV';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }
  return name.substring(0, 2).toUpperCase();
});

// Validación de reglas en tiempo real
const hasMinLength = computed(() => (form.password || '').length >= 6);
const passwordsMatch = computed(() => 
  Boolean(form.password && form.password_confirmation && form.password === form.password_confirmation)
);
const isDifferentFromCurrent = computed(() => {
  if (!form.password || !form.current_password) return false;
  return form.password !== form.current_password;
});

const canSubmit = computed(() => {
  return Boolean(
    form.current_password &&
    hasMinLength.value &&
    passwordsMatch.value &&
    isDifferentFromCurrent.value
  );
});

// Medidor de fortaleza de contraseña (0 a 4)
const strengthScore = computed(() => {
  const p = form.password || '';
  if (!p) return 0;
  let score = 0;
  if (p.length >= 6) score++;
  if (p.length >= 8) score++;
  if (/[A-Z]/.test(p) && /[a-z]/.test(p)) score++;
  if (/[0-9]/.test(p) || /[^A-Za-z0-9]/.test(p)) score++;
  return Math.min(score, 4);
});

const strengthInfo = computed(() => {
  switch (strengthScore.value) {
    case 1:
      return { label: 'Débil', colorClass: 'text-rose-500', barClass: 'bg-rose-500' };
    case 2:
      return { label: 'Aceptable', colorClass: 'text-amber-500', barClass: 'bg-amber-500' };
    case 3:
      return { label: 'Segura', colorClass: 'text-sky-500', barClass: 'bg-sky-500' };
    case 4:
      return { label: 'Muy Segura', colorClass: 'text-emerald-500', barClass: 'bg-emerald-500' };
    default:
      return { label: 'Muy Corta', colorClass: 'text-slate-400', barClass: 'bg-slate-300 dark:bg-slate-700' };
  }
});

function toggleDashboardView() {
  handleClose();
  if (isInAdmin.value) {
    router.push('/');
  } else {
    router.push('/admin');
  }
}

async function handleNotificationClick() {
  if (notificationPermission.value !== 'granted') {
    await requestNotificationPermission();
  } else {
    showSystemNotification({
      title: '🔔 Alerta de Prueba - TAMep CRM',
      body: 'Las notificaciones y el sonido del CRM están funcionando correctamente.',
      tag: 'test-profile-notification'
    });
    sendTestPushNotification().catch((e) => console.warn('Error test push:', e));
  }
}

async function handleLogoutFromModal() {
  handleClose();
  await authStore.logout();
  router.push('/login');
}

function resetForm() {
  form.current_password = '';
  form.password = '';
  form.password_confirmation = '';
  showCurrentPassword.value = false;
  showNewPassword.value = false;
  showConfirmPassword.value = false;
  errorMessage.value = '';
  successMessage.value = '';
  loading.value = false;
}

function handleClose() {
  if (loading.value) return;
  resetForm();
  emit('close');
}

watch(
  () => props.isOpen,
  (newVal) => {
    if (newVal) {
      activeTab.value = props.initialTab || 'info';
      resetForm();
      if (activeTab.value === 'password') {
        nextTick(() => {
          currentPasswordInput.value?.focus();
        });
      }
    }
  }
);

watch(
  () => activeTab.value,
  (newTab) => {
    if (newTab === 'password') {
      nextTick(() => {
        currentPasswordInput.value?.focus();
      });
    }
  }
);

async function handleSubmit() {
  if (!canSubmit.value || loading.value) return;

  loading.value = true;
  errorMessage.value = '';
  successMessage.value = '';

  try {
    const res = await authStore.updatePassword({
      current_password: form.current_password,
      password: form.password,
      password_confirmation: form.password_confirmation,
    });

    successMessage.value = res.message || 'Contraseña actualizada con éxito.';
    emit('updated');

    // Cerrar automáticamente tras 1.4 segundos de éxito
    setTimeout(() => {
      handleClose();
    }, 1400);
  } catch (err) {
    errorMessage.value = err.message || 'Error al cambiar la contraseña.';
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
  animation: fadeIn 0.2s ease-out;
}
</style>
