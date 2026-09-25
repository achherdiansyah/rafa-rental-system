import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { RecommendationPage } from './pages/RecommendationPage'
import { recommendationService } from './services/recommendationService'
import { ToastProvider } from '@/app/ToastContext'

vi.mock('./services/recommendationService', () => ({
  recommendationService: {
    requestRecommendation: vi.fn(),
    getHistory: vi.fn(),
    getRecommendation: vi.fn(),
  },
}))

const renderComponent = () => {
  return render(
    <MemoryRouter>
      <ToastProvider>
        <RecommendationPage />
      </ToastProvider>
    </MemoryRouter>
  )
}

describe('Recommendation UI Suite', () => {
  const mockRecommendationResult = {
    id: 1,
    user_id: 10,
    status: 'PROCESSED' as const,
    created_at: '2026-09-25T10:00:00Z',
    updated_at: '2026-09-25T10:00:00Z',
    criteria: {
      id: 1,
      request_id: 1,
      project_type: 'Galian Basah dan Drainase',
      terrain_condition: 'Lumpur / Rawa / Basah',
      load_capacity: 20,
      work_volume: 5000,
      depth_requirement: 4.5,
      reach_requirement: 9.5,
      duration_days: 14,
      target_productivity: null,
      location_access: null,
      budget_range: null,
      additional_params: null,
    },
    results: [
      {
        id: 101,
        request_id: 1,
        equipment_model_id: 5,
        rank: 1,
        match_score: 98.9,
        reasoning_text: 'Model Komatsu PC200-8 sangat cocok untuk galian lumpur basah dengan kapasitas 20 Ton.',
        created_at: '2026-09-25T10:00:00Z',
        model: {
          id: 5,
          brand: 'Komatsu',
          model_name: 'PC200-8',
          capacity_value: 20,
          capacity_unit: 'Ton',
          is_active: true,
          type: { id: 1, name: 'Excavator' },
          prices: [
            { id: 1, price_type: 'HOURLY', is_all_in: false, base_rate: 250000, minimum_hours: 8 },
          ],
          attachments: [],
          units_count: 3,
        } as any,
      },
    ],
  }

  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(recommendationService.getHistory).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [mockRecommendationResult],
      meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 },
    } as any)
  })

  it('renders recommendation calculator form with initial state', () => {
    renderComponent()

    expect(screen.getByRole('heading', { name: /sistem rekomendasi armada alat berat/i })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: /input kriteria proyek/i })).toBeInTheDocument()
    expect(screen.getByText(/kalkulator rekomendasi siap digunakan/i)).toBeInTheDocument()
  })

  it('validates required fields before submission', async () => {
    renderComponent()

    const submitBtn = screen.getByRole('button', { name: /dapatkan rekomendasi/i })
    fireEvent.click(submitBtn)

    await waitFor(() => {
      expect(screen.getByText(/jenis pekerjaan\/proyek wajib dipilih atau diisi/i)).toBeInTheDocument()
      expect(screen.getByText(/kondisi tanah\/medan lokasi wajib dipilih atau diisi/i)).toBeInTheDocument()
    })

    expect(recommendationService.requestRecommendation).not.toHaveBeenCalled()
  })

  it('submits valid form and renders ranked recommendation cards with explanation', async () => {
    vi.mocked(recommendationService.requestRecommendation).mockResolvedValue({
      success: true,
      message: 'Rekomendasi berhasil diproses.',
      data: mockRecommendationResult,
    } as any)

    renderComponent()

    // Fill project type
    const projectSelect = screen.getByLabelText(/jenis pekerjaan \/ proyek/i)
    fireEvent.change(projectSelect, { target: { value: 'Galian Basah dan Drainase' } })

    // Fill terrain condition
    const terrainSelect = screen.getByLabelText(/kondisi tanah \/ medan/i)
    fireEvent.change(terrainSelect, { target: { value: 'Lumpur / Rawa / Basah' } })

    // Fill capacity
    const capacityInput = screen.getByLabelText(/target beban/i)
    fireEvent.change(capacityInput, { target: { value: '20' } })

    // Submit
    const submitBtn = screen.getByRole('button', { name: /dapatkan rekomendasi/i })
    fireEvent.click(submitBtn)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /hasil analisis rekomendasi/i })).toBeInTheDocument()
      expect(screen.getByText('Komatsu PC200-8')).toBeInTheDocument()
      expect(screen.getByText(/98\.9% cocok/i)).toBeInTheDocument()
      expect(screen.getByText(/#1 pilihan/i)).toBeInTheDocument()
      expect(screen.getByText(/model komatsu pc200-8 sangat cocok/i)).toBeInTheDocument()
      expect(screen.getByRole('button', { name: /lihat detail armada/i })).toBeInTheDocument()
    })
  })

  it('handles API error when recommendation submission fails', async () => {
    vi.mocked(recommendationService.requestRecommendation).mockRejectedValueOnce({
      response: { data: { message: 'Koneksi ke scoring engine gagal.' } },
    })

    renderComponent()

    fireEvent.change(screen.getByLabelText(/jenis pekerjaan \/ proyek/i), { target: { value: 'Galian Basah dan Drainase' } })
    fireEvent.change(screen.getByLabelText(/kondisi tanah \/ medan/i), { target: { value: 'Lumpur / Rawa / Basah' } })

    fireEvent.click(screen.getByRole('button', { name: /dapatkan rekomendasi/i }))

    await waitFor(() => {
      expect(screen.getByText(/koneksi ke scoring engine gagal/i)).toBeInTheDocument()
    })
  })

  it('switches to history tab and lists past recommendation requests', async () => {
    renderComponent()

    const historyTab = screen.getByRole('button', { name: /riwayat/i })
    fireEvent.click(historyTab)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /riwayat permintaan rekomendasi/i })).toBeInTheDocument()
      expect(screen.getByText(/request #1/i)).toBeInTheDocument()
      expect(screen.getByText(/galian basah dan drainase/i)).toBeInTheDocument()
    })
  })
})
