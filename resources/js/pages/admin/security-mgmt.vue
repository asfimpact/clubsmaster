<script setup>
definePage({
  name: 'admin-security-mgmt',
  meta: {
    action: 'manage',
    subject: 'Admin',
  },
})

const settings = ref({
  '2fa_enabled': '0',
})

const loading = ref(false)
const saving = ref(false)

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
    // Show success message (using global snackbar if available, or just log)
    console.log('Settings saved!')
  } catch (error) {
    console.error('Error saving settings:', error)
  } finally {
    saving.value = false
  }
}

onMounted(fetchSettings)
</script>

<template>
  <VRow>
    <VCol cols="12" md="6">
      <VCard title="Security Configuration">
        <VCardText>
          <p class="text-body-1 mb-6">
            Global security settings for the portal. Changes applied here will reflect across all user statuses.
          </p>

          <div class="d-flex align-center justify-space-between mb-4">
            <div>
              <h6 class="text-h6 mb-1">Require 2FA (TOTP)</h6>
              <p class="text-sm text-disabled mb-0">
                When enabled, all users without a verified 2FA will be marked as "Inactive".
              </p>
            </div>
            <VSwitch
              v-model="settings['2fa_enabled']"
              true-value="1"
              false-value="0"
              @change="saveSettings"
            />
          </div>
        </VCardText>

        <VDivider />

        <VCardText class="d-flex justify-end pt-5">
          <VBtn
            :loading="saving"
            @click="saveSettings"
          >
            Save Changes
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>

    <VCol cols="12" md="6">
      <VCard title="Security Insights" variant="tonal" color="primary">
        <VCardText>
          <div class="d-flex align-center mb-4">
            <VIcon icon="tabler-info-circle" class="me-2" />
            <h6 class="text-h6">How this affects the portal:</h6>
          </div>
          
          <ul class="text-body-2 ps-6">
            <li class="mb-2"><strong>Active:</strong> Email verified and 2FA verified (if required).</li>
            <li class="mb-2"><strong>Inactive:</strong> Logged in but missing active 2FA verification.</li>
            <li class="mb-2"><strong>Pending:</strong> User has registered but not yet verified their email address.</li>
            <li><strong>Suspended:</strong> Manually blocked by an administrator.</li>
          </ul>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
