export type Theme = 'light' | 'dark'
export type FontScale = 'small' | 'medium' | 'large'

export const FONT_SCALE_ORDER: FontScale[] = ['small', 'medium', 'large']

/** Anda um passo (P/M/G) na direção informada, sem estourar as pontas. */
export function nextFontScale(current: FontScale, direction: 1 | -1): FontScale {
  const currentIndex = FONT_SCALE_ORDER.indexOf(current)
  const nextIndex = Math.min(Math.max(currentIndex + direction, 0), FONT_SCALE_ORDER.length - 1)

  return FONT_SCALE_ORDER[nextIndex]!
}

export function toggleTheme(current: Theme): Theme {
  return current === 'light' ? 'dark' : 'light'
}
