export default defineNuxtRouteMiddleware(async (to) => {
  const auth = useAuthStore()

  if (!auth.isAuthenticated) {
    await auth.fetchCurrentUser()
  }

  if (!auth.isAuthenticated && to.path !== '/login') {
    return navigateTo('/login')
  }

  if (auth.isAuthenticated && to.path === '/login') {
    return navigateTo('/')
  }
})
