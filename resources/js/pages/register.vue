<script setup>
import { VForm } from 'vuetify/components/VForm'
import AuthProvider from '@/views/pages/authentication/AuthProvider.vue'
import authV1BottomShape from '@images/svg/auth-v1-bottom-shape.svg?raw'
import authV1TopShape from '@images/svg/auth-v1-top-shape.svg?raw'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const router = useRouter()
const ability = useAbility()

const form = ref({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  password: '',
  privacyPolicies: false,
})

const errors = ref({
  first_name: undefined,
  last_name: undefined,
  email: undefined,
  phone: undefined,
  password: undefined,
})

const refVForm = ref()
const isPasswordVisible = ref(false)

const termErrors = ref([])

const register = async () => {
  try {
    const res = await $api('/auth/register', {
      method: 'POST',
      body: {
        first_name: form.value.first_name,
        last_name: form.value.last_name,
        email: form.value.email,
        phone: form.value.phone,
        password: form.value.password,
      },
      onResponseError({ response }) {
        errors.value = response._data.errors
      },
    })

    const { accessToken, userData, userAbilityRules } = res

    useCookie('userAbilityRules').value = userAbilityRules
    ability.update(userAbilityRules)
    useCookie('userData').value = userData
    useCookie('accessToken').value = accessToken

    // Redirect to index route
    await router.push('/')
  } catch (err) {
    console.error(err)
  }
}

const onSubmit = () => {
  termErrors.value = []
  
  if (!form.value.privacyPolicies) {
    termErrors.value = ['Please agree to the privacy policy']
  }

  refVForm.value?.validate().then(({ valid: isValid }) => {
    if (isValid && form.value.privacyPolicies)
      register()
  })
}
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
            Adventure starts here 🚀
          </h4>
          <p class="mb-0">
            Make your app management easy and fun!
          </p>
        </VCardText>

        <VCardText>
          <VForm
            ref="refVForm"
            @submit.prevent="onSubmit"
          >
            <VRow>
              <!-- First Name -->
              <VCol
                cols="12"
                sm="6"
              >
                <AppTextField
                  v-model="form.first_name"
                  autofocus
                  label="First Name"
                  placeholder="John"
                  :rules="[requiredValidator]"
                  :error-messages="errors.first_name"
                />
              </VCol>

              <!-- Last Name -->
              <VCol
                cols="12"
                sm="6"
              >
                <AppTextField
                  v-model="form.last_name"
                  label="Last Name"
                  placeholder="Doe"
                  :rules="[requiredValidator]"
                  :error-messages="errors.last_name"
                />
              </VCol>

              <!-- email -->
                <VCol cols="12">
                <AppTextField
                  v-model="form.email"
                  label="Email"
                  type="email"
                  placeholder="johndoe@email.com"
                  :rules="[requiredValidator, emailValidator]"
                  :error-messages="errors.email"
                />
              </VCol>

              <!-- Phone -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.phone"
                  label="Mobile Number"
                  placeholder="+1234567890"
                  :rules="[requiredValidator]"
                  :error-messages="errors.phone"
                />
              </VCol>

              <!-- password -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.password"
                  label="Password"
                  placeholder="············"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  autocomplete="password"
                  :rules="[requiredValidator, (v) => v.length >= 8 || 'The password field must be at least 8 characters']"
                  :error-messages="errors.password"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />

                <div class="my-6">
                  <VCheckbox
                    id="privacy-policy"
                    v-model="form.privacyPolicies"
                    :error-messages="termErrors"
                  >
                    <template #label>
                      <span class="me-1 text-high-emphasis">I agree to</span>
                      <a
                        href="javascript:void(0)"
                        class="text-primary"
                      >privacy policy & terms</a>
                    </template>
                  </VCheckbox>
                </div>

                <VBtn
                  block
                  type="submit"
                >
                  Sign up
                </VBtn>
              </VCol>

              <!-- login instead -->
              <VCol
                cols="12"
                class="text-center text-base"
              >
                <span>Already have an account?</span>
                <RouterLink
                  class="text-primary ms-1"
                  :to="{ name: 'login' }"
                >
                  Sign in instead
                </RouterLink>
              </VCol>

              <VCol
                cols="12"
                class="d-flex align-center"
              >
                <VDivider />
                <span class="mx-4">or</span>
                <VDivider />
              </VCol>

              <!-- auth providers -->
              <VCol
                cols="12"
                class="text-center"
              >
                <AuthProvider />
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
</style>
