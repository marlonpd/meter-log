import { useEffect, useState } from 'react'
import './App.css'

const API_URL = `${import.meta.env.VITE_API_URL ?? 'http://localhost:8000'}/api/meter-readings`

function App() {
  const [readings, setReadings] = useState([])
  const [loading, setLoading] = useState(true)
  const [editingId, setEditingId] = useState(null)
  const [date, setDate] = useState('')
  const [value, setValue] = useState('')
  const [error, setError] = useState('')

  const load = () => {
    setLoading(true)
    fetch(API_URL)
      .then((res) => res.json())
      .then(setReadings)
      .finally(() => setLoading(false))
  }

  useEffect(load, [])

  const resetForm = () => {
    setEditingId(null)
    setDate('')
    setValue('')
    setError('')
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')

    const res = await fetch(editingId ? `${API_URL}/${editingId}` : API_URL, {
      method: editingId ? 'PUT' : 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ reading_date: date, reading_value: value }),
    })

    if (!res.ok) {
      const body = await res.json()
      setError(Object.values(body.errors ?? { error: [body.message] }).flat().join(' '))
      return
    }

    resetForm()
    load()
  }

  const handleEdit = (reading) => {
    setEditingId(reading.id)
    setDate(reading.reading_date)
    setValue(reading.reading_value)
    setError('')
  }

  const handleDelete = async (reading) => {
    if (!confirm(`Delete reading from ${reading.reading_date}?`)) return
    await fetch(`${API_URL}/${reading.id}`, { method: 'DELETE' })
    load()
  }

  return (
    <section id="center">
      <h1>Meter Reading Log</h1>

      {loading ? (
        <p>Loading...</p>
      ) : (
        <>
          {readings.length === 0 && <p>No readings yet. Add your initial reading below.</p>}

          {readings.length > 0 && (
            <table>
              <thead>
                <tr>
                  <th>Previous Reading Date</th>
                  <th>Current Reading Date</th>
                  <th>Consumed (kWh)</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {readings.map((r, i) => (
                  <tr key={r.id}>
                    <td>
                      {i === 0 ? '—' : (
                        <>
                          {readings[i - 1].reading_date}
                          <br />
                          ({readings[i - 1].reading_value})
                        </>
                      )}
                    </td>
                    <td>{r.reading_date} <br /> ({r.reading_value})</td>
                    <td>{r.consumption ?? '—'}</td>
                    <td>
                      <button onClick={() => handleEdit(r)}>Edit</button>
                      <button onClick={() => handleDelete(r)}>Delete</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}

          <form onSubmit={handleSubmit}>
            <h2>{editingId ? 'Edit Reading' : readings.length === 0 ? 'Initial Reading' : 'New Reading'}</h2>
            <label>
              Date
              <input type="date" value={date} onChange={(e) => setDate(e.target.value)} required />
            </label>
            <label>
              Reading (kWh)
              <input type="number" step="0.01" min="0" value={value} onChange={(e) => setValue(e.target.value)} required />
            </label>
            {error && <p className="error">{error}</p>}
            <div>
              <button type="submit">{editingId ? 'Update' : 'Save'}</button>
              {editingId && <button type="button" onClick={resetForm}>Cancel</button>}
            </div>
          </form>
        </>
      )}
    </section>
  )
}

export default App
