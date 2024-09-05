import React from 'react'
import { UseMollieReturn } from '../hooks/useMollie'

const MollieContext = React.createContext<UseMollieReturn>({} as UseMollieReturn)
export default MollieContext
