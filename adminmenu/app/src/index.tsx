import React from 'react'
import ReactDOM from 'react-dom'
import './assets/main.css'
import { App } from '@webstollen/react-jtl-plugin/lib'
import reportWebVitals from './reportWebVitals'
import SnackbarProvider from 'react-simple-snackbar'
import tabs from './tabs'

ReactDOM.render(
  <React.StrictMode>
    <SnackbarProvider>
      <App tabs={tabs} />
    </SnackbarProvider>
  </React.StrictMode>,
  document.getElementById('root')
)

// If you want to start measuring performance in your app, pass a function
// to log results (for example: reportWebVitals(console.log))
// or send to an analytics endpoint. Learn more: https://bit.ly/CRA-vitals
reportWebVitals()
