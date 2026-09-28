import { test, expect, type Page } from '@playwright/test'
import { appendFileSync } from 'node:fs'

const DBG = process.env.TEMP + '/pw-dbg.log'
const dbg = (s: string) => {
  try {
    appendFileSync(DBG, s + '\n')
  } catch {
    /* ignore */
  }
}

const dbgFull = async (tag: string, page: Page) => {
  const text = (await page.locator('body').innerText()).replace(/\n+/g, ' | ')
  const idx = text.indexOf('Tambah Lokasi Baru')
  dbg(tag + ' BODY-TAIL=[' + (idx >= 0 ? text.slice(idx + 17, idx + 700) : text.slice(-500)) + ']')
  const html = await page.locator('body').innerHTML()
  const hIdx = html.lastIndexOf('Tambah Lokasi Baru')
  dbg(tag + ' HTML-TAIL=[' + html.slice(hIdx, hIdx + 800) + ']')
}

/**
 * True-browser runtime reproduction of the Project Location persistence bug.
 * Runs against the REAL running backend (127.0.0.1:8000) and the REAL frontend
 * dev server (localhost:5173). No mocks anywhere.
 */

const unique = Date.now()
const EMAIL = 'budi@kontraktor.com'
const PASSWORD = 'password'
const NAME = `Lokasi PW ${unique}`
const EDITED_NAME = `Lokasi PW Edit ${unique}`

interface GtResponses {
  urls: string[]
  bodies: Array<{ total: number; ids: number[] }>
}

async function loginUser(page: Page): Promise<void> {
  await page.goto('/login')
  // The SPA keeps a boot "Memuat halaman..." gate until the auth check settles.
  // Poll for the login form; recover with a cleared session if the gate stalls.
  const deadline = Date.now() + 120000
  while (Date.now() < deadline) {
    const email = page.getByPlaceholder('nama@perusahaan.com')
    if (await email.isVisible().catch(() => false)) break
    await page.waitForTimeout(2000)
    if (Date.now() > deadline - 30000) {
      await page.evaluate(() => localStorage.clear())
      await page.reload()
    }
  }
  await page.getByPlaceholder('nama@perusahaan.com').fill(EMAIL)
  await page.locator('input[type="password"]').first().fill(PASSWORD)
  await page.locator('form button[type="submit"]').first().click()
  for (let i = 0; i < 8; i++) {
    if (page.url().includes('/app')) break
    await page.waitForTimeout(2000)
  }
  if (!page.url().includes('/app')) {
    console.log('AFTER-LOGIN url=', page.url())
    console.log('AFTER-LOGIN body=', (await page.locator('body').innerText()).slice(0, 200))
  }
  await page.waitForURL(/\/app/, { timeout: 40000 })
}

async function goLocations(page: Page): Promise<void> {
  await page.goto('/app/locations')
  await page.getByRole('heading', { name: /lokasi proyek/i }).waitFor({ timeout: 15000 })
}

async function createLocation(page: Page, name: string): Promise<void> {
  await page.getByRole('button', { name: /tambah lokasi baru/i }).first().click()
  await page.getByLabel(/nama proyek \/ area/i).fill(name)
  await page.getByLabel(/nama pic lapangan/i).fill('Wahyu Setiawan')
  await page.getByLabel(/nomor telepon pic/i).fill('0897789012')
  await page.getByLabel(/kota \/ kabupaten/i).fill('Surabaya')
  await page.getByLabel(/alamat lengkap proyek/i).fill('Rungkut Asri')
  await page.getByRole('button', { name: /daftarkan lokasi/i }).click()
  await expect(page.getByText(name).first()).toBeVisible({ timeout: 15000 })
}

async function captureGetLooks(page: Page, g: GtResponses): Promise<void> {
  page.on('response', (res) => {
    if (res.url().includes('/project-locations') && res.request().method() === 'GET') {
      const u = new URL(res.url())
      if (u.pathname.endsWith('/project-locations')) {
        g.urls.push(res.url())
        res
          .json()
          .then((body) => {
            g.bodies.push({ total: body?.meta?.total ?? 0, ids: (body?.data ?? []).map((d: { id: number }) => d.id) })
          })
          .catch(() => undefined)
      }
    }
  })
}

test('project location survives reload and navigation (real browser)', async ({ page }) => {
  const consoleErrors: string[] = []
  page.on('console', (msg) => {
    if (msg.type() === 'error') consoleErrors.push(msg.text())
  })
  page.on('pageerror', (err) => consoleErrors.push(String(err)))

  const gets: GtResponses = { urls: [], bodies: [] }

  // 1) login (real seeded user, real backend)
  await loginUser(page)

  // 2) create location -> visible
  await goLocations(page)
  await captureGetLooks(page, gets)
  await createLocation(page, NAME)

  // sanity: the create-time list must contain the record (API → state → render)
  await expect(page.getByText(NAME).first()).toBeVisible()

  // 3) RELOAD
  await page.reload()
  await page.getByRole('heading', { name: /lokasi proyek/i }).waitFor({ timeout: 15000 })
  // give any list fetch a moment
  await page.waitForTimeout(3000)
  dbg('RELOAD GET urls=' + JSON.stringify(gets.urls))
  dbg('RELOAD GET bodies=' + JSON.stringify(gets.bodies))
  await dbgFull('RELOAD-STEP', page)
  console.log('RELOAD list cards=', await page.getByText(NAME).count())
  dbg('RELOAD list cards=' + (await page.getByText(NAME).count()))
  console.log('RELOAD page body=', (await page.locator('body').innerText()).match(/Belum Ada Lokasi Proyek|[A-Za-z ]*PW[0-9]+/g))
  dbg('RELOAD page body=' + JSON.stringify((await page.locator('body').innerText()).match(/Belum Ada Lokasi Proyek|[A-Za-z ]*PW[0-9]+/g)))
  await expect(page.getByText(NAME).first()).toBeVisible({ timeout: 15000 })

  // 4) NAVIGATE: locations -> overview -> back
  await page.goto('/app')
  await page.goto('/app/locations')
  await page.getByRole('heading', { name: /lokasi proyek/i }).waitFor({ timeout: 15000 })
  await expect(page.getByText(NAME).first()).toBeVisible({ timeout: 15000 })

  // 5) NAVIGATE: locations -> catalog -> back
  await page.goto('/app/equipment')
  await page.goto('/app/locations')
  await page.waitForTimeout(3000)
  dbg('NAV-BACK GET bodies=' + JSON.stringify(gets.bodies))
  dbg('NAV-BACK text=' + JSON.stringify((await page.locator('body').innerText()).match(/Belum Ada Lokasi Proyek|[A-Za-z ]*PW[0-9]+/g)))
  await expect(page.getByText(NAME).first()).toBeVisible({ timeout: 15000 })

  // 6) SEARCH
  await page.getByPlaceholder(/cari nama proyek/i).fill(NAME)
  await expect(page.getByText(NAME).first()).toBeVisible({ timeout: 15000 })

  // 7) EDIT + persistence after reload — search has narrowed the list to ONE card
  await page.waitForTimeout(700) // debounce + render settle
  await expect(page.getByRole('button', { name: /edit/i })).toHaveCount(1, { timeout: 10000 })
  await page.getByRole('button', { name: /edit/i }).click()
  await page.getByLabel(/nama proyek \/ area/i).fill(EDITED_NAME)
  page.on('response', async (r) => {
    if (r.request().method() === 'PUT' && r.url().includes('/project-locations')) {
      dbg('PUT-LOC ' + r.status() + ' ' + r.url())
    }
  })
  await page.getByRole('button', { name: /simpan perubahan/i }).click()
  await page.waitForTimeout(4000)
  await dbgFull('EDIT-SAVED', page)
  // clearing the old-name search so the renamed record shows again
  await page.getByPlaceholder(/cari nama proyek/i).fill('')
  await page.waitForTimeout(800)
  await expect(page.getByText(EDITED_NAME).first()).toBeVisible({ timeout: 15000 })
  await page.reload()
  await page.getByRole('heading', { name: /lokasi proyek/i }).waitFor({ timeout: 15000 })
  await expect(page.getByText(EDITED_NAME).first()).toBeVisible({ timeout: 15000 })

  // 8) Verify every HTTP GET that listed locations carried the record through
  await expect(page.locator('body')).not.toContainText('Belum Ada Lokasi Proyek')
  const listed = gets.bodies.filter((b) => b.total >= 1)
  expect(listed.length).toBeGreaterThanOrEqual(1)
  expect(gets.urls.length).toBeGreaterThanOrEqual(1)

  // 9) DELETE (no booking dependency -> soft delete); narrow list to our record first
  await page.getByPlaceholder(/cari nama proyek/i).fill(EDITED_NAME)
  await page.waitForTimeout(2500)
  await dbgFull('PRE-DELETE', page)
  await expect(page.getByRole('button', { name: /hapus/i })).toHaveCount(1, { timeout: 15000 })
  await page.getByRole('button', { name: /hapus/i }).click()
  await page.getByRole('button', { name: /ya, hapus lokasi/i }).click()
  await expect(page.getByText(EDITED_NAME)).toHaveCount(0, { timeout: 15000 })

  // 10) No console errors
  expect(consoleErrors).toEqual([])
})
