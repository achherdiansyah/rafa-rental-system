import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import App from './App'

describe('App Root', () => {
  it('renders the application shell without crashing', async () => {
    render(<App />)
    
    // We expect the Navbar/Hero to render the text "RAFA Rental"
    const heading = await screen.findAllByText(/RAFA Rental/i, undefined, { timeout: 15000 })
    expect(heading.length).toBeGreaterThan(0)
  }, 20000)
})
