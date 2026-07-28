// Pages accessibles SANS être connecté (login + parcours mot de passe oublié).
const PUBLIC_PAGES = new Set(['/login', '/forgot-password', '/reset-password'])

export default defineNuxtRouteMiddleware((to) => {
  const auth = useAuthStore()
  const isPublicPage = PUBLIC_PAGES.has(to.path)

  if (!auth.isAuthenticated && !isPublicPage) {
    return navigateTo('/login')
  }
  // Un utilisateur déjà connecté n'a rien à faire sur le login (les autres pages publiques
  // — reset via lien email — restent accessibles, ex. réinitialiser depuis une autre session).
  if (auth.isAuthenticated && to.path === '/login') {
    return navigateTo('/')
  }
})
