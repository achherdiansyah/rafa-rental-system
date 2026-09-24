import { BrowserRouter } from 'react-router-dom'
import { AuthProvider } from './app/AuthContext'
import { ToastProvider } from './app/ToastContext'
import { AppRoutes } from './routes/AppRoutes'

function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <ToastProvider>
          <AppRoutes />
        </ToastProvider>
      </AuthProvider>
    </BrowserRouter>
  )
}

export default App
