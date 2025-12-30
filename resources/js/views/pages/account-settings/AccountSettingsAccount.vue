<script setup>
import avatar1 from '@images/avatars/avatar-1.png'

// 1. Get user data from cookie
const userData = useCookie('userData')

// 2. Initialize local state from cookie (safe clone)
// We split FullName back to First/Last for editing if needed, 
// BUT simpler is to rely on what backend gives or just ask user to edit First/Last separately if we have them.
// The userData cookie usually has 'fullName'. 
// Let's assume we want to edit First/Last names. 
// If the cookie only has 'fullName', we might need to fetch the detailed 'user' object from /api/user to get first/last.
// However, looking at AuthController logic, we return `userData` with `fullName`.
// We should probably fetch the FRESH user details on mount to be safe and get first_name/last_name.
// OR, we can just split fullName for now if we are lazy, but that's risky.

// Better approach: Fetch /api/user on mount to fill the form.
const accountDataLocal = ref({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  avatarImg: avatar1, 
})

const isConfirmDialogOpen = ref(false)
const isAccountDeactivated = ref(false)
const validateAccountDeactivation = [v => !!v || 'Please confirm account deactivation']

// Fetch fresh data
const fetchUser = async () => {
  try {
    const { data } = await useApi('/user') // This route exists in api.php and returns $request->user()
    if (data.value) {
        accountDataLocal.value.first_name = data.value.first_name
        accountDataLocal.value.last_name = data.value.last_name
        accountDataLocal.value.email = data.value.email
        accountDataLocal.value.phone = data.value.phone || data.value.mobile // handle both keys if any
        // accountDataLocal.value.avatarImg = data.value.avatar // if we had one
    }
  } catch (e) {
    console.error("Failed to fetch user", e)
  }
}

onMounted(() => {
    fetchUser()
})

const changeAvatar = file => {
  const fileReader = new FileReader()
  const { files } = file.target
  if (files && files.length) {
    fileReader.readAsDataURL(files[0])
    fileReader.onload = () => {
      if (typeof fileReader.result === 'string')
        accountDataLocal.value.avatarImg = fileReader.result
    }
  }
}

const resetAvatar = () => {
  accountDataLocal.value.avatarImg = avatar1
}

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
        
        // Update cookie with new display data
        if (res.userData) {
            userData.value = res.userData
        }
        
        alert('Profile updated successfully') // Simple alert for now, can be snackbar
    } catch (e) {
        console.error(e)
        alert('Failed to update profile')
    }
}

const resetForm = () => {
  fetchUser() // Just re-fetch
}
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard title="Profile Details">
        <VCardText class="d-flex">
          <!-- 👉 Avatar -->
          <VAvatar
            rounded
            size="100"
            class="me-6"
            :image="accountDataLocal.avatarImg"
          />

          <!-- 👉 Upload Photo -->
          <form class="d-flex flex-column justify-center gap-4">
            <div class="d-flex flex-wrap gap-4">
              <VBtn
                color="primary"
                size="small"
                @click="refInputEl?.click()"
              >
                <VIcon
                  icon="tabler-cloud-upload"
                  class="d-sm-none"
                />
                <span class="d-none d-sm-block">Upload new photo</span>
              </VBtn>

              <input
                ref="refInputEl"
                type="file"
                name="file"
                accept=".jpeg,.png,.jpg,GIF"
                hidden
                @input="changeAvatar"
              >

              <VBtn
                type="reset"
                size="small"
                color="secondary"
                variant="tonal"
                @click="resetAvatar"
              >
                <span class="d-none d-sm-block">Reset</span>
                <VIcon
                  icon="tabler-refresh"
                  class="d-sm-none"
                />
              </VBtn>
            </div>

            <p class="text-body-1 mb-0">
              Allowed JPG, GIF or PNG. Max size of 800K
            </p>
          </form>
        </VCardText>

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
      <VCard title="Delete Account">
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
