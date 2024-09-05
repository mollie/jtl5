import { useSnackbar } from 'react-simple-snackbar'

const useWarningSnack = () =>
  useSnackbar({
    position: 'top-right',
    style: {
      backgroundColor: '#FF8800',
      color: 'black',
      fontSize: '20px',
      textAlign: 'center',
      padding: '10px',
    },
  })

export default useWarningSnack
