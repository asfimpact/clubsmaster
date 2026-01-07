<script setup>
// 1. Get user data from cookie
const userData = useCookie('userData')

// 2. Initialize local state from cookie (instant display!)
const accountDataLocal = ref({
  first_name: userData.value?.first_name || '',
  last_name: userData.value?.last_name || '',
  email: userData.value?.email || '',
  phone: userData.value?.phone || '',
})

const isConfirmDialogOpen = ref(false)
const isAccountDeactivated = ref(false)
const validateAccountDeactivation = [v => !!v || 'Please confirm account deactivation']

// Fetch fresh data in background
const fetchUser = async () => {
  try {
    const { data } = await useApi('/user')
    if (data.value) {
      // Update local form
      accountDataLocal.value.first_name = data.value.first_name
      accountDataLocal.value.last_name = data.value.last_name
      accountDataLocal.value.email = data.value.email
      accountDataLocal.value.phone = data.value.phone || ''
      
      // Sync cookie to keep everything consistent
      userData.value = data.value
    }
  } catch (e) {
    console.error("Failed to fetch user", e)
  }
}

onMounted(() => {
  // Fetch fresh data in background (form already shows cookie data)
  fetchUser()
})

const saveChanges = async () => {
  try {
    const res = await $api('/auth/profile-update', {
      method: 'POST',
      body: {
        first_name: accountDataLocal.value.first_name,
        last_name: accountDataLocal.value.last_name,
        phone: accountDataLocal.value.phone,
      }
    })
    
    // Update cookie with new data
    if (res.userData) {
      userData.value = res.userData
    }
    
    alert('Profile updated successfully')
  } catch (e) {
    console.error(e)
    alert('Failed to update profile')
  }
}

const resetForm = () => {
  fetchUser() // Re-fetch to reset
}
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard title="Profile Details">
        <VCardText class="pt-2">
          <!-- 👉 Form -->
          <VForm class="mt-3" @submit.prevent="saveChanges">
            <VRow>
              <!-- 👉 First Name -->
              <VCol
                md="6"
                cols="12"
              >
                <AppTextField
                  v-model="accountDataLocal.first_name"
                  placeholder="John"
                  label="First Name"
                />
              </VCol>

              <!-- 👉 Last Name -->
              <VCol
                md="6"
                cols="12"
              >
                <AppTextField
                  v-model="accountDataLocal.last_name"
                  placeholder="Doe"
                  label="Last Name"
                />
              </VCol>

              <!-- 👉 Email -->
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="accountDataLocal.email"
                  label="E-mail"
                  placeholder="johndoe@gmail.com"
                  type="email"
                  disabled 
                  hint="Contact admin to change email"
                />
              </VCol>

              <!-- 👉 Phone -->
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="accountDataLocal.phone"
                  label="Phone Number"
                  placeholder="+1 (917) 543-9876"
                />
              </VCol>

              <!-- 👉 Form Actions -->
              <VCol
                cols="12"
                class="d-flex flex-wrap gap-4"
              >
                <VBtn type="submit">Save changes</VBtn>

                <VBtn
                  color="secondary"
                  variant="tonal"
                  @click.prevent="resetForm"
                >
                  Reset
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>

    <VCol cols="12">
      <!-- 👉 Delete Account -->
      <VCard title="Delete Account - Coming soon">
        <VCardText>
          <!-- 👉 Checkbox and Button  -->
          <div>
            <VCheckbox
              v-model="isAccountDeactivated"
              :rules="validateAccountDeactivation"
              label="I confirm my account deactivation"
            />
          </div>

          <VBtn
            :disabled="!isAccountDeactivated"
            color="error"
            class="mt-6"
            @click="isConfirmDialogOpen = true"
          >
            Deactivate Account
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>

  <!-- Confirm Dialog -->
  <ConfirmDialog
    v-model:is-dialog-visible="isConfirmDialogOpen"
    confirmation-question="Are you sure you want to deactivate your account?"
    confirm-title="Deactivated!"
    confirm-msg="Your account has been deactivated successfully."
    cancel-title="Cancelled"
    cancel-msg="Account Deactivation Cancelled!"
  />
</template>
