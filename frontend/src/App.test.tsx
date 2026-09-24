import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import App from './App'

describe('App Root', () => {
  it('renders the application shell without crashing', async () => {
    render(<App />)
    
    // We expect the Navbar/Hero to render the text "RAFA Rental"
    const heading = await screen.findAllByText(/RAFA Rental/i)
    expect(heading.length).toBeGreaterThan(0)
  })
})
