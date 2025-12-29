<script setup>
definePage({
  name: 'admin-email-settings',
  meta: {
    action: 'manage',
    subject: 'Admin',
  },
})

const settings = ref({
  'mail_host': '',
  'mail_port': '587',
  'mail_username': '',
  'mail_password': '',
  'mail_encryption': 'tls',
  'mail_from_address': '',
  'mail_from_name': 'ClubMaster',
})

const loading = ref(false)
const saving = ref(false)
const testing = ref(false)
const testEmailAddress = ref('')
const testResult = ref({ message: '', color: '' })

const fetchSettings = async () => {
  loading.value = true
  try {
    const response = await $api('/admin/settings')
    settings.value = { ...settings.value, ...response }
  } catch (error) {
    console.error('Error fetching settings:', error)
  } finally {
    loading.value = false
  }
}

const saveSettings = async () => {
  saving.value = true
  try {
    await $api('/admin/settings', {
      method: 'PATCH',
      body: { settings: settings.value },
    })
    testResult.value = { message: 'Settings saved successfully!', color: 'success' }
  } catch (error) {
    console.error('Error saving settings:', error)
    testResult.value = { message: 'Failed to save settings.', color: 'error' }
  } finally {
    saving.value = false
  }
}

const sendTestEmail = async () => {
  if (!testEmailAddress.value) return
  
  testing.value = true
  testResult.value = { message: '', color: '' }
  
  try {
    const response = await $api('/admin/settings/test-email', {
      method: 'POST',
      body: { email: testEmailAddress.value },
    })
    testResult.value = { message: response.message, color: 'success' }
  } catch (error) {
    console.error('Error sending test email:', error)
    const errorMsg = error.response?._data?.message || 'SMTP Configuration Error. Check host/port/credentials.'
    testResult.value = { message: errorMsg, color: 'error' }
  } finally {
    testing.value = false
  }
}

onMounted(fetchSettings)
</script>

<template>
  <VRow>
    <VCol cols="12" md="8">
      <VCard title="SMTP Server Configuration">
        <VCardText>
          <p class="text-body-1 mb-6">
            Configure your outgoing email server. These settings are used for system notifications and Two-Step Verification codes.
          </p>

          <VForm @submit.prevent="saveSettings">
            <VRow>
              <!-- Host -->
              <VCol cols="12" md="9">
                <AppTextField
                  v-model="settings['mail_host']"
                  label="SMTP Host"
                  placeholder="smtp.example.com"
                />
              </VCol>

              <!-- Port -->
              <VCol cols="12" md="3">
                <AppTextField
                  v-model="settings['mail_port']"
                  label="Port"
                  placeholder="587"
                />
              </VCol>

              <!-- Username -->
              <VCol cols="12" md="6">
                <AppTextField
                  v-model="settings['mail_username']"
                  label="Username"
                  placeholder="user@example.com"
                />
              </VCol>

              <!-- Password -->
              <VCol cols="12" md="6">
                <AppTextField
                  v-model="settings['mail_password']"
                  label="Password"
                  type="password"
                  placeholder="············"
                />
              </VCol>

              <!-- Encryption -->
              <VCol cols="12" md="6">
                <AppSelect
                  v-model="settings['mail_encryption']"
                  label="Encryption"
                  :items="['tls', 'ssl', 'none']"
                />
              </VCol>

              <VCol cols="12">
                <VDivider class="my-2" />
              </VCol>

              <!-- From Address -->
              <VCol cols="12" md="6">
                <AppTextField
                  v-model="settings['mail_from_address']"
                  label="From Email Address"
                  placeholder="noreply@clubmaster.com"
                />
              </VCol>

              <!-- From Name -->
              <VCol cols="12" md="6">
                <AppTextField
                  v-model="settings['mail_from_name']"
                  label="From Name"
                  placeholder="ClubMaster System"
                />
              </VCol>
            </VRow>

            <div class="d-flex justify-end gap-3 mt-6">
              <VBtn
                :loading="saving"
                type="submit"
              >
                Save Configuration
              </VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 Test Connection Card -->
    <VCol cols="12" md="4">
      <VCard title="Test Connection">
        <VCardText>
          <p class="text-body-2 mb-4">
            Send a test email to verify your SMTP settings are working correctly.
          </p>

          <AppTextField
            v-model="testEmailAddress"
            label="Recipient Email"
            placeholder="your-email@example.com"
            class="mb-4"
          />

          <VBtn
            block
            variant="elevated"
            color="primary"
            prepend-icon="tabler-send"
            :disabled="!testEmailAddress"
            :loading="testing"
            class="mt-2"
            @click="sendTestEmail"
          >
            Send Test Message
          </VBtn>

          <!-- Status Message -->
          <VAlert
            v-if="testResult.message"
            :color="testResult.color"
            variant="tonal"
            class="mt-4"
            density="compact"
            :icon="testResult.color === 'success' ? 'tabler-circle-check' : 'tabler-circle-x'"
          >
            {{ testResult.message }}
          </VAlert>
        </VCardText>

        <VDivider />

        <VCardText>
          <div class="d-flex align-center gap-2 text-warning">
            <VIcon icon="tabler-alert-triangle" size="20" />
            <span class="text-xs font-weight-medium">Note: Save settings before testing.</span>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
