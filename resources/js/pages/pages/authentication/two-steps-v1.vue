<script setup>
import authV1BottomShape from '@images/svg/auth-v1-bottom-shape.svg?raw'
import authV1TopShape from '@images/svg/auth-v1-top-shape.svg?raw'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

definePage({
  name: 'pages-authentication-two-steps-v1',
  meta: {
    layout: 'blank',
    public: true, // Needs to be public so the landing page can redirect here, but protected by logic
  },
})

const router = useRouter()
const userData = useCookie('userData')
const otp = ref('')
const loading = ref(false)
const resending = ref(false)
const errorMessage = ref('')

const sendCode = async () => {
  resending.value = true
  errorMessage.value = ''
  try {
    await $api('/auth/2fa-send', { method: 'POST' })
  } catch (error) {
    errorMessage.value = 'Failed to send code. Please try again.'
  } finally {
    resending.value = false
  }
}

const verifyCode = async () => {
  if (loading.value || otp.value.length !== 6) return
  
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await $api('/auth/2fa-verify', {
      method: 'POST',
      body: { code: otp.value },
    })
    
    // 1. Force update local user data from response or fetch fresh
    if (response.userData) {
        userData.value = response.userData
        useCookie('userData').value = response.userData
    } else {
        // Fallback: manually patch if backend didn't send full object
        if (userData.value) userData.value.status = 'Active'
    }

    // 2. Wait a tick to ensure cookie is set
    await nextTick()
    
    // 3. Redirect
    window.location.href = '/' // Hard reload to bypass any stuck router state
  } catch (error) {
    const data = error.response?._data
    errorMessage.value = data?.message || 'Invalid or expired code.'
    if (data?.debug) {
      errorMessage.value += ` | Debug: User ${data.debug.user_id}, Sent: ${data.debug.sent_code}, Stored: ${data.debug.stored_code}, Match: ${data.debug.is_match}, Expiry: ${data.debug.is_not_expired}`
    }
    otp.value = ''
  } finally {
    loading.value = false
  }
}

const onFinish = () => {
  verifyCode()
}

onMounted(() => {
  if (!userData.value) {
    router.push('/login')
  }
})
</script>

<template>
  <div class="auth-wrapper d-flex align-center justify-center pa-4">
    <div class="position-relative my-sm-16">
      <!-- 👉 Top shape -->
      <VNodeRenderer
        :nodes="h('div', { innerHTML: authV1TopShape })"
        class="text-primary auth-v1-top-shape d-none d-sm-block"
      />

      <!-- 👉 Bottom shape -->
      <VNodeRenderer
        :nodes="h('div', { innerHTML: authV1BottomShape })"
        class="text-primary auth-v1-bottom-shape d-none d-sm-block"
      />

      <!-- 👉 Auth card -->
      <VCard
        class="auth-card"
        max-width="460"
        :class="$vuetify.display.smAndUp ? 'pa-6' : 'pa-0'"
      >
        <VCardItem class="justify-center">
          <VCardTitle>
            <RouterLink to="/">
              <div class="app-logo">
                <VNodeRenderer :nodes="themeConfig.app.logo" />
                <h1 class="app-logo-title">
                  {{ themeConfig.app.title }}
                </h1>
              </div>
            </RouterLink>
          </VCardTitle>
        </VCardItem>

        <VCardText>
          <h4 class="text-h4 mb-1">
            Two Step Verification 💬
          </h4>
          <p class="mb-1">
            We sent a verification code to your email. Enter the code in the field below to access your account.
          </p>
          <h6 class="text-h6">
            {{ userData?.email }}
          </h6>
        </VCardText>

        <VCardText>
          <VAlert
            v-if="errorMessage"
            color="error"
            variant="tonal"
            class="mb-4"
          >
            {{ errorMessage }}
          </VAlert>

          <VForm @submit.prevent="verifyCode">
            <VRow>
              <VCol cols="12">
                <h6 class="text-body-1">
                  Type your 6 digit security code
                </h6>
                <VOtpInput
                  v-model="otp"
                  :disabled="loading"
                  type="number"
                  class="pa-0"
                />
              </VCol>

              <VCol cols="12">
                <VBtn
                  :loading="loading"
                  block
                  type="submit"
                >
                  Verify my account
                </VBtn>
              </VCol>

              <VCol cols="12">
                <div class="d-flex justify-center align-center flex-wrap">
                  <span class="me-1">Didn't get the code?</span>
                  <a
                    href="javascript:void(0)"
                    :class="{ 'opacity-50 pointer-events-none': resending }"
                    @click="sendCode"
                  >
                    {{ resending ? 'Sending...' : 'Resend' }}
                  </a>
                </div>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </div>
  </div>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth";

.v-otp-input {
  .v-otp-input__content {
    padding-inline: 0;
  }
}
</style>
