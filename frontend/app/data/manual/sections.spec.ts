import { describe, expect, it } from 'vitest'
import { manualSections, manualSectionsForRole } from './sections'

describe('manualSections', () => {
  it('has no duplicate ids', () => {
    const ids = manualSections.map((section) => section.id)
    expect(new Set(ids).size).toBe(ids.length)
  })

  it('every section has a title, icon and component defined', () => {
    for (const section of manualSections) {
      expect(section.title.length).toBeGreaterThan(0)
      expect(section.icon).toBeTruthy()
      expect(section.component).toBeTruthy()
    }
  })
})

describe('manualSectionsForRole', () => {
  it('includes sections without a role restriction for a guest (undefined role)', () => {
    const ids = manualSectionsForRole(undefined).map((section) => section.id)
    expect(ids).toContain('introducao')
    expect(ids).toContain('pdv')
    expect(ids).not.toContain('backup')
    expect(ids).not.toContain('configuracoes')
  })

  it('admin sees every section', () => {
    expect(manualSectionsForRole('admin')).toHaveLength(manualSections.length)
  })

  it('seller only sees sections without a role restriction', () => {
    const ids = manualSectionsForRole('seller').map((section) => section.id)
    expect(ids).not.toContain('caixa')
    expect(ids).not.toContain('financeiro')
    expect(ids).not.toContain('configuracoes')
    expect(ids).not.toContain('backup')
    expect(ids).not.toContain('instalacao')
  })

  it('cashier sees caixa/financeiro but not admin-only sections', () => {
    const ids = manualSectionsForRole('cashier').map((section) => section.id)
    expect(ids).toContain('caixa')
    expect(ids).toContain('financeiro')
    expect(ids).not.toContain('configuracoes')
    expect(ids).not.toContain('backup')
  })
})
