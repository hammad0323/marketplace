// A spinning circle shown while data is loading from the database
function Loader({ text = 'Loading...' }) {
  return (
    <div className="loader" role="status">
      <span className="spinner" />
      <span>{text}</span>
    </div>
  );
}

export default Loader;
