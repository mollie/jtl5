import { useSnackbar } from 'react-simple-snackbar'

const useSuccessSnack = () =>
  useSnackbar({
    position: 'top-right',
    style: {
      backgroundColor: 'green',
      color: 'white',
      fontSize: '20px',
      textAlign: 'center',
      padding: '10px',
    },
  })

export default useSuccessSnack
