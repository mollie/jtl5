import { useSnackbar } from 'react-simple-snackbar'

const useErrorSnack = () =>
  useSnackbar({
    position: 'top-right',
    style: {
      backgroundColor: '#FF0000',
      color: 'white',
      fontSize: '20px',
      textAlign: 'center',
      padding: '10px',
    },
  })

export default useErrorSnack
