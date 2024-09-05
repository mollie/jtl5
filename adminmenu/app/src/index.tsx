import React from 'react'
import ReactDOM from 'react-dom'
import './assets/main.css'
import { App } from '@webstollen/react-jtl-plugin/lib'
import reportWebVitals from './reportWebVitals'
import SnackbarProvider from 'react-simple-snackbar'
import createTabs from "./tabs";
import {PaymentMethodProvider} from "./context/PaymentMethodContext";

ReactDOM.render(
  <React.StrictMode>
    <SnackbarProvider>
      <PaymentMethodProvider>
        <App createTabs={createTabs}/>
      </PaymentMethodProvider>
    </SnackbarProvider>
  </React.StrictMode>,
  document.getElementById('root')
)

// If you want to start measuring performance in your app, pass a function
// to log results (for example: reportWebVitals(console.log))
// or send to an analytics endpoint. Learn more: https://bit.ly/CRA-vitals
reportWebVitals()
