// A red box shown when something goes wrong (e.g. the database is offline)
function ErrorMessage({ message }) {
  return (
    <div className="error-box" role="alert">
      <strong>⚠️ Something went wrong</strong>
      <p>{message}</p>
    </div>
  );
}

export default ErrorMessage;
